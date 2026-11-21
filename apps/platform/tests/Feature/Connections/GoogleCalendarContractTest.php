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
use App\Models\CalendarConflict;
use App\Models\CalendarEvent;
use App\Models\Connection;
use App\Models\IntegrationSetting;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\Calendar\CalendarConnections;
use App\Services\Calendar\CalendarEvents;
use App\Services\Connections\ConnectionAuthorizer;
use App\Services\Connections\Connections;
use App\Services\Connections\SyncEngine;
use Carbon\CarbonImmutable;
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
    app(CredentialVault::class)->put('google-calendar', CredentialEnvironment::Sandbox, ['client_id' => 'test-client.apps.googleusercontent.com', 'client_secret' => 'test-secret'], null);
    IntegrationSetting::query()->create(['integration_id' => 'google-calendar', 'mode' => IntegrationMode::Sandbox]);
}

/** Connects Maria's test Google account through the real sign-in path. */
function connectGoogle(User $user): Connection
{
    $authorizer = app(ConnectionAuthorizer::class);
    $url = $authorizer->start($user, 'google-calendar', 'user', $user->id, 'web', HistoryRange::ThreeMonths);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    $result = $authorizer->complete('google-calendar', (string) $query['state'], '4/0AX4XfWh-test-code', null);
    expect($result['outcome'])->toBe('connected');

    return Connection::query()->sole();
}

beforeEach(fn () => onGoogleSandbox());

it('sends people to Google with PKCE, offline access and calendar-only scopes', function () {
    $user = User::factory()->create();
    $url = app(ConnectionAuthorizer::class)->start($user, 'google-calendar', 'user', $user->id, 'web', HistoryRange::ThreeMonths);

    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray([
        'response_type' => 'code',
        'client_id' => 'test-client.apps.googleusercontent.com',
        'redirect_uri' => url('integrations/google-calendar/callback'),
        'scope' => 'openid email https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.calendarlist.readonly',
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
        ->and(json_decode((string) $connection->fresh()?->cursor('events'), true))->toBe(['tokens' => ['primary' => 'CPDAlvWDx70CEPDAlvWDx70CGAU=']]);

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
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => google('calendar-list'),
    ]);
    $connection = connectGoogle(User::factory()->create());

    expect(app(SyncEngine::class)->run($connection->fresh(), 'events', SyncTrigger::Scheduled))->toBe('synced');

    // Sign-in (2), first sync (1), the list of calendars (1), the refused sync token (1), then a full sync again from the history start (1).
    Http::assertSentCount(6);
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
    $url = $authorizer->start($user, 'google-calendar', 'user', $user->id, 'web', HistoryRange::ThreeMonths);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    Queue::fake();
    $authorizer->complete('google-calendar', (string) $query['state'], 'code', null);
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

    $channel = $connection?->meta['channels'][0]['id'];
    expect($channel)->toStartWith("reos-{$connection?->id}-")
        ->and($connection?->meta['channels'][0])->toMatchArray(['resource' => 'primary', 'resource_id' => 'o3hgv1538sdjfh']);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/events/watch')
        && $r['address'] === 'https://app.reos.test/webhooks/google-calendar' && $r['token'] === GoogleCalendarChannels::token((string) $channel));

    Queue::fake();
    $push = fn (string $token, string $number, string $state = 'exists') => $this->call('POST', '/webhooks/google-calendar', [], [], [], [
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
    expect(GoogleCalendarDriver::SCOPES)->toContain('https://www.googleapis.com/auth/calendar.events')->not->toContain('https://www.googleapis.com/auth/calendar.readonly')
        ->and(GoogleCalendarChannels::connectionFor('reos-42-abc123', GoogleCalendarChannels::token('reos-42-abc123')))->toBe(42)
        ->and(GoogleCalendarChannels::connectionFor('reos-42-abc123', 'nope'))->toBeNull();
});

/*
 * Two-way (B4.1, ADR 0017): writing to Google, versions and conflicts,
 * calendars to choose from, and push channels kept renewed.
 */

/**
 * A fixture with its placeholders filled in.
 *
 * @param  array<string, string>  $fill
 * @return array<string, mixed>
 */
function googleJson(string $name, array $fill = []): array
{
    return (array) json_decode(strtr((string) file_get_contents(base_path("tests/Fixtures/google-calendar/{$name}.json")), $fill), true);
}

/**
 * Google for one test: sign-in, the calendar list, and the main calendar's
 * events, where POST adds, PATCH changes (412 when `$patch` says so) and GET
 * reads one event.
 *
 * @param  array<string, mixed>  $state  Shared with the test: `patch` (list of responses), `get`.
 */
function fakeGoogle(array &$state): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => google('token'),
        'oauth2.googleapis.com/revoke' => Http::response('', 200),
        'openidconnect.googleapis.com/v1/userinfo' => google('userinfo'),
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => google('calendar-list'),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => function (Request $request) use (&$state) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $id = basename($path);

            return match (true) {
                str_ends_with($path, '/watch') => isset($state['watch']) ? $state['watch']($request) : google('watch'),
                $request->method() === 'POST' => $state['post'] ?? Http::response(googleJson('event-inserted', [
                    'PLACEHOLDER_ID' => (string) $request['id'],
                    'PLACEHOLDER_REOS_ID' => (string) $request['extendedProperties']['private']['reos_event_id'],
                ])),
                $request->method() === 'PATCH' => array_shift($state['patch']) ?? Http::response(googleJson('event-patched', ['PLACEHOLDER_ID' => $id])),
                $request->method() === 'DELETE' => Http::response('', 204),
                str_ends_with($path, '/events') => $state['list'] ?? Http::response(['kind' => 'calendar#events', 'nextSyncToken' => 'sync-1', 'items' => []]),
                default => $state['get'] ?? Http::response(googleJson('event-inserted', ['PLACEHOLDER_ID' => $id, 'PLACEHOLDER_REOS_ID' => '0'])),
            };
        },
    ]);
}

/** Maria's showing, made in REOS and written to Google. */
function showing(User $maria): CalendarEvent
{
    return app(CalendarEvents::class)->create($maria, [
        'title' => 'Showing — 2218 Elm St',
        'kind' => 'showing',
        'starts_at' => CarbonImmutable::parse('2026-11-24T11:00:00-06:00')->utc(),
        'ends_at' => CarbonImmutable::parse('2026-11-24T11:45:00-06:00')->utc(),
        'location' => '2218 Elm St, Houston, TX',
        'attendees' => ['Nguyen.Family@example.com'],
    ])->fresh() ?? throw new RuntimeException('Event not saved.');
}

describe('two-way', function () {
    beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-11-21T15:00:00Z')));

    it('lists the calendars, main first, and keeps only the times from a calendar shared as busy only', function () {
        $state = [];
        fakeGoogle($state);
        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/c_8d1f0a3b2e%40group.calendar.google.com/events*' => Http::response(['nextSyncToken' => 'family-1', 'items' => [
                ['id' => 'fam1', 'status' => 'confirmed', 'summary' => 'Soccer practice', 'location' => 'Memorial Park', 'updated' => '2026-11-20T10:00:00Z',
                    'attendees' => [['email' => 'coach@example.com']], 'start' => ['dateTime' => '2026-11-22T17:00:00-06:00'], 'end' => ['dateTime' => '2026-11-22T18:30:00-06:00']],
                ['id' => 'fam2', 'status' => 'confirmed', 'summary' => 'Free: call grandma', 'transparency' => 'transparent', 'updated' => '2026-11-20T10:00:00Z',
                    'start' => ['dateTime' => '2026-11-23T17:00:00-06:00'], 'end' => ['dateTime' => '2026-11-23T17:30:00-06:00']],
            ]]),
        ]);
        $maria = User::factory()->create();
        $connection = connectGoogle($maria)->fresh();

        expect(array_column($connection?->meta['calendars'] ?? [], 'id'))->toBe(['primary', 'c_8d1f0a3b2e@group.calendar.google.com', 'en.usa#holiday@group.v.calendar.google.com'])
            ->and($connection?->meta['calendars'][1])->toBe(['id' => 'c_8d1f0a3b2e@group.calendar.google.com', 'name' => 'Family (shared)', 'primary' => false, 'writable' => true])
            ->and($connection?->meta['calendars'][2]['writable'])->toBeFalse()
            // The main calendar is read in full and receives REOS's events; the rest are off until chosen.
            ->and($connection?->settings['write_calendar'] ?? null)->toBe('primary');

        app(CalendarConnections::class)->choose((clone $connection), ['c_8d1f0a3b2e@group.calendar.google.com' => 'busy'], null);

        $family = CalendarEvent::query()->where('calendar_id', 'c_8d1f0a3b2e@group.calendar.google.com')->sole();
        expect($family)->busy->toBeTrue()->title->toBeNull()->location->toBeNull()->attendees->toBe([])
            ->and($family->starts_at->toIso8601String())->toBe('2026-11-22T23:00:00+00:00')
            ->and(app(CalendarEvents::class)->editable($family))->toBeFalse();
    });

    it('writes an event made in REOS to the chosen calendar, once, with REOS\'s id and without emailing guests', function () {
        $state = [];
        fakeGoogle($state);
        $maria = User::factory()->create();
        connectGoogle($maria);

        $event = showing($maria);

        $insert = Http::recorded(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/calendars/primary/events?'))->sole()[0];
        expect($insert->url())->toContain('sendUpdates=none')
            ->and($insert['id'])->toMatch('/^reos[0-9a-f]{32}$/')
            ->and($insert['summary'])->toBe('Showing — 2218 Elm St')
            ->and($insert['start'])->toBe(['dateTime' => '2026-11-24T17:00:00+00:00', 'date' => null, 'timeZone' => 'UTC'])
            ->and($insert['attendees'])->toBe([['email' => 'nguyen.family@example.com']])
            ->and($insert['extendedProperties'])->toBe(['private' => ['reos_event_id' => (string) $event->id]]);
        expect($event)->external_id->toBe($insert['id'])->etag->toBe('"3471200000000000"')->push_state->toBeNull()
            ->html_link->toBe('https://www.google.com/calendar/event?eid=cmVvcy10ZXN0');

        // Google's echo of REOS's own write changes nothing.
        $state['list'] = Http::response(['nextSyncToken' => 'sync-2', 'items' => [googleJson('event-inserted', ['PLACEHOLDER_ID' => (string) $event->external_id, 'PLACEHOLDER_REOS_ID' => (string) $event->id])]]);
        app(SyncEngine::class)->run(Connection::query()->sole(), 'events', SyncTrigger::Webhook);
        expect(SyncRun::query()->latest('id')->first()?->upserted)->toBe(0)
            ->and(CalendarEvent::query()->count())->toBe(1);
    });

    it('finds the event after a lost answer instead of adding it twice (409)', function () {
        $state = ['post' => Http::response(['error' => ['code' => 409, 'message' => 'The requested identifier already exists.']], 409)];
        fakeGoogle($state);
        $maria = User::factory()->create();
        connectGoogle($maria);

        $event = showing($maria);

        expect($event->external_id)->toMatch('/^reos[0-9a-f]{32}$/')->and($event->push_state)->toBeNull();
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/events/'.$event->external_id));
    });

    it('changes only the version REOS saw; when Google\'s change is newer, Google\'s stays and REOS\'s can be undone', function () {
        $state = [];
        fakeGoogle($state);
        $maria = User::factory()->create();
        connectGoogle($maria);
        $event = showing($maria);

        $this->travel(1)->hour();
        // Maria moved it in Google five minutes after her REOS change.
        $state['patch'] = [Http::response(googleJson('precondition-failed'), 412)];
        $state['get'] = Http::response(googleJson('event-changed-in-google', [
            'PLACEHOLDER_ID' => (string) $event->external_id, 'PLACEHOLDER_REOS_ID' => (string) $event->id, 'PLACEHOLDER_UPDATED' => now()->addMinutes(5)->toIso8601String(),
        ]));
        $this->actingAs($maria)->putJson("/api/v1/calendar/events/{$event->id}", [
            'title' => 'Showing — 2218 Elm St (REOS)', 'starts_at' => '2026-11-24T12:00:00-06:00', 'ends_at' => '2026-11-24T12:45:00-06:00',
        ])->assertOk();

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->hasHeader('If-Match', '"3471200000000000"'));
        expect(Http::recorded(fn (Request $r) => $r->method() === 'PATCH'))->toHaveCount(1);
        $conflict = CalendarConflict::query()->sole();
        expect($event->fresh())->title->toBe('Showing — 2218 Elm St (Maria moved it in Google)')->push_state->toBeNull()->etag->toBe('"3471200000005555"')
            ->and($conflict)->kept->toBe('tool')->reason->toBe('edited_both')
            ->and($conflict->losing['title'])->toBe('Showing — 2218 Elm St (REOS)');

        $from = '2026-11-24T00:00:00-06:00';
        $this->actingAs($maria)->getJson('/api/v1/calendar/events?'.http_build_query(['from' => $from, 'to' => '2026-11-25T00:00:00-06:00']))
            ->assertOk()
            ->assertJsonPath('data.0.conflict.kept', 'tool')
            ->assertJsonPath('data.0.conflict.losing.title', 'Showing — 2218 Elm St (REOS)')
            ->assertJsonPath('data.0.conflict.until', $conflict->created_at?->addDays(30)->toIso8601String());

        // Undo: REOS's version becomes the newest change and goes to Google over Google's version.
        $this->actingAs($maria)->postJson("/api/v1/calendar/conflicts/{$conflict->id}/undo")->assertOk()
            ->assertJsonPath('data.title', 'Showing — 2218 Elm St (REOS)')
            ->assertJsonPath('data.conflict', null);
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->hasHeader('If-Match', '"3471200000005555"') && $r['summary'] === 'Showing — 2218 Elm St (REOS)');
        expect($conflict->fresh()?->undone_at)->not->toBeNull()->and($event->fresh()?->push_state)->toBeNull();
    });

    it('writes REOS\'s change over Google\'s when REOS\'s is newer, and keeps Google\'s version', function () {
        $state = [];
        fakeGoogle($state);
        $maria = User::factory()->create();
        connectGoogle($maria);
        $event = showing($maria);

        $this->travel(1)->hour();
        $state['patch'] = [Http::response(googleJson('precondition-failed'), 412)];
        $state['get'] = Http::response(googleJson('event-changed-in-google', [
            'PLACEHOLDER_ID' => (string) $event->external_id, 'PLACEHOLDER_REOS_ID' => (string) $event->id, 'PLACEHOLDER_UPDATED' => now()->subMinutes(5)->toIso8601String(),
        ]));
        app(CalendarEvents::class)->update($event, ['title' => 'Showing — 2218 Elm St (REOS)']);

        $patches = Http::recorded(fn (Request $r) => $r->method() === 'PATCH')->map(fn (array $pair) => $pair[0])->values();
        expect($patches)->toHaveCount(2)
            ->and($patches[1]->header('If-Match'))->toBe(['"3471200000005555"'])
            ->and($patches[1]['summary'])->toBe('Showing — 2218 Elm St (REOS)')
            ->and($event->fresh())->push_state->toBeNull()->etag->toBe('"3471200000000999"')
            ->and(CalendarConflict::query()->sole())->kept->toBe('reos')
            ->and(CalendarConflict::query()->sole()->losing['title'])->toBe('Showing — 2218 Elm St (Maria moved it in Google)');
    });

    it('deletes only what REOS made, in Google too, and Undo puts it back', function () {
        $state = [];
        fakeGoogle($state);
        $maria = User::factory()->create();
        connectGoogle($maria);
        $event = showing($maria);
        $google = CalendarEvent::query()->create(['user_id' => $maria->id, 'connection_id' => Connection::query()->sole()->id, 'source' => 'google-calendar', 'calendar_id' => 'primary', 'external_id' => 'g1', 'title' => 'Team meeting', 'starts_at' => now(), 'ends_at' => now()->addHour()]);

        $this->actingAs($maria)->deleteJson("/api/v1/calendar/events/{$google->id}")->assertUnprocessable()->assertJsonValidationErrors(['event' => 'REOS only deletes events made in REOS.']);
        $this->actingAs($maria)->deleteJson("/api/v1/calendar/events/{$event->id}")->assertNoContent();

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/events/'.$event->external_id.'?sendUpdates=none'));
        expect(CalendarEvent::withTrashed()->find($event->id))->trashed()->toBeTrue()->external_id->toBeNull();

        $this->actingAs($maria)->postJson("/api/v1/calendar/events/{$event->id}/restore")->assertOk()->assertJsonPath('data.deletable', true);
        expect(Http::recorded(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/events?')))->toHaveCount(2)
            ->and($event->fresh())->external_id->not->toBeNull()->push_state->toBeNull();
    });

    it('asks to reconnect when the sign-in doesn\'t allow writing yet, and writes the waiting change after reconnecting', function () {
        Notification::fake();
        $state = ['post' => Http::response(googleJson('insufficient-permissions'), 403)];
        fakeGoogle($state);
        $maria = User::factory()->create();
        connectGoogle($maria);

        $event = showing($maria);

        expect($event->push_state)->toBe('pending')
            ->and(Connection::query()->sole()->status)->toBe(ConnectionStatus::ReconnectNeeded);

        unset($state['post']);
        $authorizer = app(ConnectionAuthorizer::class);
        $url = $authorizer->start($maria, 'google-calendar', 'user', $maria->id, 'web', HistoryRange::ThreeMonths);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $authorizer->complete('google-calendar', (string) $query['state'], 'code-2', null);

        expect($event->fresh())->push_state->toBeNull()->external_id->not->toBeNull();
    });

    it('opens a push channel per synced calendar, renews it a day before it ends, and stops ones no longer needed', function () {
        URL::forceRootUrl('https://app.reos.test');
        URL::forceScheme('https');
        $state = [];
        fakeGoogle($state);
        $watches = [];
        $state['watch'] = function (Request $r) use (&$watches) {
            $watches[] = $r->url();

            return Http::response(['kind' => 'api#channel', 'id' => $r['id'], 'resourceId' => 'res-'.count($watches), 'expiration' => (string) now()->addDays(7)->getTimestampMs()]);
        };
        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/c_8d1f0a3b2e%40group.calendar.google.com/events/watch' => $state['watch'],
            'www.googleapis.com/calendar/v3/channels/stop' => Http::response('', 204),
            'www.googleapis.com/calendar/v3/calendars/c_8d1f0a3b2e%40group.calendar.google.com/events*' => Http::response(['nextSyncToken' => 'f', 'items' => []]),
        ]);
        $maria = User::factory()->create();
        $connection = connectGoogle($maria)->fresh();
        expect($connection?->meta['channels'])->toHaveCount(1);

        app(CalendarConnections::class)->choose((clone $connection), ['c_8d1f0a3b2e@group.calendar.google.com' => 'full'], null);
        expect(array_column(Connection::query()->sole()->meta['channels'], 'resource'))->toBe(['primary', 'c_8d1f0a3b2e@group.calendar.google.com']);

        // Six and a half days on: both end within a day and are renewed, new first.
        $this->travel(158)->hours();
        $this->artisan('reos:connections:channels')->assertSuccessful();
        $channels = Connection::query()->sole()->meta['channels'];
        expect(array_column($channels, 'resource_id'))->toBe(['res-3', 'res-4'])
            ->and(Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/channels/stop')))->toHaveCount(2);

        // The family calendar switched off: its channel stops.
        app(CalendarConnections::class)->choose(Connection::query()->sole(), ['c_8d1f0a3b2e@group.calendar.google.com' => 'off'], null);
        expect(array_column(Connection::query()->sole()->meta['channels'], 'resource'))->toBe(['primary'])
            ->and(Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/channels/stop') && $r['id'] === $channels[1]['id']))->toHaveCount(1);
    });
});
