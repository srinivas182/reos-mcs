<?php

declare(strict_types=1);

use App\Connections\ConnectionStatus;
use App\Connections\Drivers\GoogleCalendar\GoogleCalendarChannels;
use App\Connections\Drivers\GoogleCalendar\GoogleCalendarDriver;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\HistoryRange;
use App\Connections\SyncTrigger;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Jobs\SyncConnection;
use App\Models\CalendarEvent;
use App\Models\Connection;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\Connections\ConnectionAuthorizer;
use App\Services\Connections\Connections;
use App\Services\Connections\SyncEngine;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Promises\LazyPromise;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/*
 * Contract tests (ADR 0013): the real Google Calendar driver against recorded
 * responses in tests/Fixtures/google-calendar. Nothing calls Google.
 */

function google(string $name, int $status = 200): LazyPromise|PromiseInterface
{
    return Http::response((string) file_get_contents(base_path("tests/Fixtures/google-calendar/{$name}.json")), $status, ['Content-Type' => 'application/json']);
}

function onGoogleSandbox(): void
{
    app(CredentialVault::class)->put('google-workspace', CredentialEnvironment::Sandbox, ['client_id' => 'test-client.apps.googleusercontent.com', 'client_secret' => 'test-secret'], null);
    IntegrationSetting::query()->create(['integration_id' => 'google-workspace', 'mode' => IntegrationMode::Sandbox]);
}

/** Connects Maria's test Google account through the real sign-in path. */
function connectGoogle(User $user): Connection
{
    $authorizer = app(ConnectionAuthorizer::class);
    $url = $authorizer->start($user, 'google-workspace', 'user', $user->id, 'web', HistoryRange::ThreeMonths);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    $result = $authorizer->complete('google-workspace', (string) $query['state'], '4/0AX4XfWh-test-code', null);
    expect($result['outcome'])->toBe('connected');

    return Connection::query()->sole();
}

beforeEach(fn () => onGoogleSandbox());

it('sends people to Google with PKCE, offline access and read-only calendar scope', function () {
    $user = User::factory()->create();
    $url = app(ConnectionAuthorizer::class)->start($user, 'google-workspace', 'user', $user->id, 'web', HistoryRange::ThreeMonths);

    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray([
        'response_type' => 'code',
        'client_id' => 'test-client.apps.googleusercontent.com',
        'redirect_uri' => url('integrations/google-workspace/callback'),
        'scope' => 'openid email https://www.googleapis.com/auth/calendar.readonly',
        'code_challenge_method' => 'S256',
        'access_type' => 'offline',
        'prompt' => 'consent',
    ])->and($query['code_challenge'])->toMatch('/^[A-Za-z0-9_-]{43}$/');
});

it('swaps the code with the verifier, then pages through the first sync', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => google('token'),
        'openidconnect.googleapis.com/v1/userinfo' => google('userinfo'),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::sequence()->push(json_decode((string) file_get_contents(base_path('tests/Fixtures/google-calendar/events-page-1.json')), true))->push(json_decode((string) file_get_contents(base_path('tests/Fixtures/google-calendar/events-page-2.json')), true)),
    ]);

    $connection = connectGoogle(User::factory()->create());

    expect($connection->account_label)->toBe('maria.lopez.test@gmail.com')
        ->and($connection->external_account_id)->toBe('109876543210987654321')
        ->and($connection->refresh_token)->toBe('1//0g-test-refresh-token')
        ->and(CalendarEvent::query()->count())->toBe(3)
        ->and(CalendarEvent::query()->where('all_day', true)->value('title'))->toBe('Texas REALTORS® conference')
        ->and(json_decode((string) $connection->fresh()?->cursor('events'), true))->toBe(['sync' => 'CPDAlvWDx70CEPDAlvWDx70CGAU=']);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/token'
        && $r['grant_type'] === 'authorization_code' && strlen((string) $r['code_verifier']) === 64);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'timeMin=') && $r->hasHeader('Authorization', 'Bearer ya29.a0-test-access-token'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'pageToken=CkkKOwo5Y2FsZW5kYXItZXZlbnRzLXBhZ2UtMg'));
});

it('applies changes with the sync token: cancelled events go, moved ones update', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => google('token'),
        'openidconnect.googleapis.com/v1/userinfo' => google('userinfo'),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::sequence()
            ->push(json_decode((string) file_get_contents(base_path('tests/Fixtures/google-calendar/events-page-1.json')), true))
            ->push(json_decode((string) file_get_contents(base_path('tests/Fixtures/google-calendar/events-page-2.json')), true))
            ->push(json_decode((string) file_get_contents(base_path('tests/Fixtures/google-calendar/events-incremental.json')), true)),
    ]);
    $connection = connectGoogle(User::factory()->create());

    app(SyncEngine::class)->run($connection->fresh(), 'events', SyncTrigger::Scheduled);

    expect(CalendarEvent::query()->count())->toBe(2)
        ->and(CalendarEvent::query()->where('external_id', '9a8b7c6d5e4f3g2h1i0j9k8l7m')->value('title'))->toBe('Closing — Johnson family (moved)');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'syncToken=CPDAlvWDx70CEPDAlvWDx70CGAU') && ! str_contains($r->url(), 'timeMin'));
});

it('starts again when Google says the sync token expired (410)', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => google('token'),
        'openidconnect.googleapis.com/v1/userinfo' => google('userinfo'),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::sequence()
            ->push(json_decode((string) file_get_contents(base_path('tests/Fixtures/google-calendar/events-page-2.json')), true))
            ->push(json_decode((string) file_get_contents(base_path('tests/Fixtures/google-calendar/gone.json')), true), 410)
            ->push(json_decode((string) file_get_contents(base_path('tests/Fixtures/google-calendar/events-page-2.json')), true)),
    ]);
    $connection = connectGoogle(User::factory()->create());

    expect(app(SyncEngine::class)->run($connection->fresh(), 'events', SyncTrigger::Scheduled))->toBe('synced');

    // Sign-in (2), first sync (1), the refused sync token (1), then a full sync again from the history start (1).
    Http::assertSentCount(5);
    $events = Http::recorded(fn (Request $r) => str_contains($r->url(), '/events?'))->map(fn (array $pair) => $pair[0]->url())->values();
    expect($events[1])->toContain('syncToken=')
        ->and($events[2])->toContain('timeMin=')->not->toContain('syncToken=');
});

it('refreshes a refused access token once, and asks to reconnect when the refresh is refused too', function () {
    Notification::fake();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::sequence()
            ->push(json_decode((string) file_get_contents(base_path('tests/Fixtures/google-calendar/token.json')), true))
            ->push(json_decode((string) file_get_contents(base_path('tests/Fixtures/google-calendar/invalid-grant.json')), true), 400),
        'openidconnect.googleapis.com/v1/userinfo' => google('userinfo'),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401),
    ]);

    $user = User::factory()->create();
    $authorizer = app(ConnectionAuthorizer::class);
    $url = $authorizer->start($user, 'google-workspace', 'user', $user->id, 'web', HistoryRange::ThreeMonths);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    Queue::fake();
    $authorizer->complete('google-workspace', (string) $query['state'], 'code', null);
    $connection = Connection::query()->sole();

    expect(app(SyncEngine::class)->run($connection, 'events', SyncTrigger::First))->toBe('failed')
        ->and($connection->fresh()?->status)->toBe(ConnectionStatus::ReconnectNeeded);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/token' && $r['grant_type'] === 'refresh_token');
});

it('backs off when Google rate-limits', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => google('token'),
        'openidconnect.googleapis.com/v1/userinfo' => google('userinfo'),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => google('rate-limited', 403),
    ]);
    $user = User::factory()->create();
    Queue::fake();
    connectGoogle($user);

    expect(fn () => app(SyncEngine::class)->run(Connection::query()->sole(), 'events', SyncTrigger::First))->toThrow(VendorRateLimited::class);
});

it('subscribes to push changes when REOS has a public address, and accepts only genuine pushes', function () {
    URL::forceRootUrl('https://app.reos.test');
    URL::forceScheme('https');
    Http::fake([
        'oauth2.googleapis.com/token' => google('token'),
        'openidconnect.googleapis.com/v1/userinfo' => google('userinfo'),
        'www.googleapis.com/calendar/v3/calendars/primary/events/watch' => google('watch'),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => google('events-page-2'),
    ]);
    $connection = connectGoogle(User::factory()->create())->fresh();

    $channel = $connection?->meta['channel_id'];
    expect($channel)->toStartWith("reos-{$connection?->id}-")
        ->and($connection?->meta['resource_id'])->toBe('o3hgv1538sdjfh');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/events/watch')
        && $r['address'] === 'https://app.reos.test/webhooks/google-workspace' && $r['token'] === GoogleCalendarChannels::token((string) $channel));

    Queue::fake();
    $push = fn (string $token, string $number, string $state = 'exists') => $this->call('POST', '/webhooks/google-workspace', [], [], [], [
        'HTTP_X_GOOG_CHANNEL_ID' => $channel,
        'HTTP_X_GOOG_CHANNEL_TOKEN' => $token,
        'HTTP_X_GOOG_MESSAGE_NUMBER' => $number,
        'HTTP_X_GOOG_RESOURCE_STATE' => $state,
    ]);

    $push(GoogleCalendarChannels::token((string) $channel), '1', 'sync')->assertStatus(202);
    Queue::assertNothingPushed();
    $push(GoogleCalendarChannels::token((string) $channel), '2')->assertStatus(202);
    $push(GoogleCalendarChannels::token((string) $channel), '2')->assertOk();
    $push('forged-token', '3')->assertStatus(401);
    Queue::assertPushed(SyncConnection::class, 1);
});

it('revokes the token at Google on disconnect', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => google('token'),
        'oauth2.googleapis.com/revoke' => Http::response('', 200),
        'openidconnect.googleapis.com/v1/userinfo' => google('userinfo'),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => google('events-page-2'),
    ]);
    $user = User::factory()->create();
    $connection = connectGoogle($user);

    app(Connections::class)->disconnect($connection, $user);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/revoke' && $r['token'] === '1//0g-test-refresh-token');
    expect(Connection::query()->exists())->toBeFalse();
});

it('reads Google time formats, including all-day events', function () {
    expect(GoogleCalendarDriver::SCOPES)->toContain('https://www.googleapis.com/auth/calendar.readonly')
        ->and(GoogleCalendarChannels::connectionFor('reos-42-abc123', GoogleCalendarChannels::token('reos-42-abc123')))->toBe(42)
        ->and(GoogleCalendarChannels::connectionFor('reos-42-abc123', 'nope'))->toBeNull();
});
