<?php

declare(strict_types=1);

use App\Connections\ConnectionStatus;
use App\Connections\Drivers\OutlookCalendar\OutlookCalendarChannels;
use App\Connections\Drivers\OutlookCalendar\OutlookCalendarDriver;
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
use App\Models\User;
use App\Services\Calendar\CalendarConnections;
use App\Services\Calendar\CalendarEvents;
use App\Services\Connections\ConnectionAuthorizer;
use App\Services\Connections\Connections;
use App\Services\Connections\SyncEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/*
 * Contract tests (ADR 0013, ADR 0017): the real Outlook Calendar driver
 * against Microsoft Graph's documented answers in tests/Fixtures/outlook-calendar.
 * Nothing calls Microsoft.
 */

/**
 * @param  array<string, string>  $fill
 * @return array<string, mixed>
 */
function outlookJson(string $name, array $fill = []): array
{
    return (array) json_decode(strtr((string) file_get_contents(base_path("tests/Fixtures/outlook-calendar/{$name}.json")), $fill), true);
}

/**
 * Graph for one test. `$state` can hold: `delta` (list of responses for
 * calendar-view delta, in order), `post`, `patch` (list), `get`, `subscribe`.
 *
 * @param  array<string, mixed>  $state
 */
function fakeOutlook(array &$state): void
{
    Http::fake([
        'login.microsoftonline.com/common/oauth2/v2.0/token' => $state['token'] ?? Http::response(outlookJson('token')),
        'graph.microsoft.com/v1.0/me?*' => Http::response(outlookJson('me')),
        'graph.microsoft.com/v1.0/me/calendars?*' => Http::response(outlookJson('calendars')),
        'graph.microsoft.com/v1.0/subscriptions*' => function (Request $request) use (&$state) {
            if ($request->method() === 'DELETE') {
                return Http::response('', 204);
            }

            $state['subscriptions'] = [...($state['subscriptions'] ?? []), $request->data()];

            return Http::response(outlookJson('subscription', [
                'PLACEHOLDER_CLIENT_STATE' => (string) $request['clientState'],
                'PLACEHOLDER_EXPIRES' => (string) $request['expirationDateTime'],
            ]), 201);
        },
        'graph.microsoft.com/v1.0/*' => function (Request $request) use (&$state) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            // Every matching fake runs; the ones above answer these.
            if ($path === '/v1.0/me' || str_starts_with($path, '/v1.0/subscriptions') || $path === '/v1.0/me/calendars') {
                return null;
            }

            return match (true) {
                str_contains($path, '/calendarView/delta') => outlookNext($state, 'delta') ?? Http::response(['@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/calendar/calendarView/delta?$deltatoken=empty', 'value' => []]),
                $request->method() === 'POST' => $state['post'] ?? Http::response([
                    ...outlookJson('event-created', ['PLACEHOLDER_TRANSACTION' => (string) ($request->data()['transactionId'] ?? '')]),
                    // Each new event its own id: the first keeps the fixture's.
                    'id' => 'AAMkAGI2TGuLNEW='.str_repeat('x', ($state['posts'] = ($state['posts'] ?? 0) + 1) - 1),
                ], 201),
                $request->method() === 'PATCH' => outlookNext($state, 'patch') ?? Http::response(outlookJson('event-created', ['PLACEHOLDER_TRANSACTION' => 'x'])),
                $request->method() === 'DELETE' => Http::response('', 204),
                default => $state['get'] ?? Http::response(outlookJson('event-created', ['PLACEHOLDER_TRANSACTION' => 'x'])),
            };
        },
    ]);
}

/**
 * The next queued answer for a kind of call, or null.
 *
 * @param  array<string, mixed>  $state
 */
function outlookNext(array &$state, string $kind): mixed
{
    if (! isset($state[$kind]) || ! is_array($state[$kind]) || $state[$kind] === []) {
        return null;
    }

    return array_shift($state[$kind]);
}

/** Dana's Microsoft 365 account, connected through the real sign-in path. */
function connectOutlook(User $user): Connection
{
    $authorizer = app(ConnectionAuthorizer::class);
    $url = $authorizer->start($user, 'outlook-calendar', 'user', $user->id, 'web', HistoryRange::ThreeMonths);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($authorizer->complete('outlook-calendar', (string) $query['state'], 'M.C512-test-code', null)['outcome'])->toBe('connected');

    return Connection::query()->sole();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-21T15:00:00Z'));
    app(CredentialVault::class)->put('outlook-calendar', CredentialEnvironment::Sandbox, ['client_id' => '24d3b144-21ae-4080-943f-7067b395b913', 'client_secret' => 'test-secret'], null);
    IntegrationSetting::query()->create(['integration_id' => 'outlook-calendar', 'mode' => IntegrationMode::Sandbox]);
});

it('signs in with Microsoft (work or personal) with PKCE and calendar-only scopes', function () {
    $user = User::factory()->create();
    $url = app(ConnectionAuthorizer::class)->start($user, 'outlook-calendar', 'user', $user->id, 'web', HistoryRange::ThreeMonths);

    expect($url)->toStartWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray([
        'client_id' => '24d3b144-21ae-4080-943f-7067b395b913',
        'redirect_uri' => url('integrations/outlook-calendar/callback'),
        'scope' => 'openid email offline_access User.Read Calendars.ReadWrite',
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
    ])->and(OutlookCalendarDriver::SCOPES)->not->toContain('Mail.Read');
});

it('reads the main calendar through delta pages, in UTC, and keeps the delta link', function () {
    $state = ['delta' => [Http::response(outlookJson('delta-page-1')), Http::response(outlookJson('delta-page-2'))]];
    fakeOutlook($state);
    $dana = User::factory()->create();

    $connection = connectOutlook($dana)->fresh();

    expect($connection?->account_label)->toBe('dana.kim@lonestarrealty.example')
        ->and($connection?->meta['calendars'][0])->toBe(['id' => 'primary', 'name' => 'dana.kim@lonestarrealty.example', 'primary' => true, 'writable' => true])
        ->and(collect($connection?->meta['calendars'])->firstWhere('name', 'United States holidays')['writable'])->toBeFalse()
        ->and(json_decode((string) $connection?->cursor('events'), true))->toBe(['tokens' => ['primary' => 'https://graph.microsoft.com/v1.0/me/calendar/calendarView/delta?$deltatoken=R0usmci39OQxqJrxK4']]);

    $listing = CalendarEvent::query()->where('external_id', 'AAMkAGI2TG93AAA=')->sole();
    expect($listing)->title->toBe('Listing appointment — 1402 Heights Blvd')
        ->description->toBe("Bring the CMA.\nPark on the street.")
        ->attendees->toBe(['pat.hill@example.com'])
        ->etag->toBe('W/"ZlnW4RIAV06KYYwlrfNZvQAALfZeRQ=="')
        ->and($listing->starts_at->toIso8601String())->toBe('2026-11-24T16:00:00+00:00');
    expect(CalendarEvent::query()->where('all_day', true)->sole())
        ->title->toBe('Texas REALTORS® conference')
        ->and(CalendarEvent::query()->where('all_day', true)->sole()->ends_at->toDateString())->toBe('2026-12-05');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/me/calendar/calendarView/delta?startDateTime=2026-08-21T00%3A00%3A00Z&endDateTime=2028-11-21T15%3A00%3A00Z')
        && $r->hasHeader('Prefer', 'odata.maxpagesize=100, outlook.timezone="UTC"'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '$skiptoken=R0usmcCM996atia_s'));
});

it('applies changes from the delta link, and starts again when it expires (410)', function () {
    $state = ['delta' => [
        Http::response(outlookJson('delta-page-1')),
        Http::response(outlookJson('delta-page-2')),
        Http::response(outlookJson('delta-changes')),
        Http::response(outlookJson('gone'), 410),
        Http::response(outlookJson('delta-page-2')),
    ]];
    fakeOutlook($state);
    connectOutlook(User::factory()->create());

    app(SyncEngine::class)->run(Connection::query()->sole(), 'events', SyncTrigger::Webhook);
    expect(CalendarEvent::query()->pluck('title')->all())->toBe(['Listing appointment — 1402 Heights Blvd (moved)']);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '$deltatoken=R0usmci39OQxqJrxK4'));

    expect(app(SyncEngine::class)->run(Connection::query()->sole(), 'events', SyncTrigger::Scheduled))->toBe('synced');
    $deltas = Http::recorded(fn (Request $r) => str_contains($r->url(), 'calendarView/delta'))->map(fn (array $pair) => $pair[0]->url())->values();
    expect($deltas->last())->toContain('startDateTime=');
});

it('adds an event REOS made with a repeatable transaction id, and lists guests only when they should be invited', function () {
    $state = [];
    fakeOutlook($state);
    $dana = User::factory()->create();
    connectOutlook($dana);

    $event = app(CalendarEvents::class)->create($dana, [
        'title' => 'Showing — 2218 Elm St', 'kind' => 'showing',
        'starts_at' => CarbonImmutable::parse('2026-11-24T17:00:00Z'), 'ends_at' => CarbonImmutable::parse('2026-11-24T17:45:00Z'),
        'location' => '2218 Elm St, Houston, TX', 'attendees' => ['nguyen.family@example.com'],
    ])->fresh();

    $create = Http::recorded(fn (Request $r) => $r->method() === 'POST' && str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/me/calendar/events'))->sole()[0];
    expect($create['transactionId'])->toMatch('/^reos-[0-9a-f]{40}$/')
        ->and($create['start'])->toBe(['dateTime' => '2026-11-24T17:00:00', 'timeZone' => 'UTC'])
        ->and($create['isAllDay'])->toBeFalse()
        ->and($create['attendees'])->toBe([])
        ->and($create->header('Prefer'))->toBe(['outlook.timezone="UTC"']);
    expect($event)->external_id->toBe('AAMkAGI2TGuLNEW=')->push_state->toBeNull()
        ->etag->toBe('W/"DwAAABYAAABS/NhmmNy2TqjuB2zQxIpXAAAAAA1E"')
        // Outlook holds no guests (no invitations asked for); REOS keeps its list.
        ->attendees->toBe(['nguyen.family@example.com']);

    app(CalendarEvents::class)->update($event, ['notify_guests' => true, 'title' => 'Showing — 2218 Elm St (with the Nguyens)']);
    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r['attendees'] === [['emailAddress' => ['address' => 'nguyen.family@example.com'], 'type' => 'required']]);

    // All-day: midnight to midnight.
    app(CalendarEvents::class)->create($dana, ['title' => 'Option period ends', 'kind' => 'deadline', 'all_day' => true, 'starts_at' => CarbonImmutable::parse('2026-11-27'), 'ends_at' => CarbonImmutable::parse('2026-11-28')]);
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && ($r->data()['isAllDay'] ?? null) === true
        && $r['start'] === ['dateTime' => '2026-11-27T00:00:00', 'timeZone' => 'UTC'] && $r['end']['dateTime'] === '2026-11-28T00:00:00');
});

it('changes only the version REOS saw; Outlook\'s newer change stays and REOS\'s can be undone', function () {
    $state = [];
    fakeOutlook($state);
    $dana = User::factory()->create();
    connectOutlook($dana);
    $event = app(CalendarEvents::class)->create($dana, ['title' => 'Showing — 2218 Elm St', 'starts_at' => CarbonImmutable::parse('2026-11-24T17:00:00Z'), 'ends_at' => CarbonImmutable::parse('2026-11-24T17:45:00Z')])->fresh();

    $this->travel(1)->hour();
    $state['patch'] = [Http::response(outlookJson('precondition-failed'), 412)];
    $state['get'] = Http::response(outlookJson('event-changed', ['PLACEHOLDER_UPDATED' => now()->addMinutes(5)->utc()->format('Y-m-d\TH:i:s.0000000\Z')]));
    app(CalendarEvents::class)->update($event, ['title' => 'Showing — 2218 Elm St (REOS)']);

    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with(rawurldecode((string) parse_url($r->url(), PHP_URL_PATH)), '/me/events/AAMkAGI2TGuLNEW=') && $r->hasHeader('If-Match', 'W/"DwAAABYAAABS/NhmmNy2TqjuB2zQxIpXAAAAAA1E"'));
    expect($event->fresh())->title->toBe('Showing — 2218 Elm St (Dana moved it in Outlook)')->push_state->toBeNull()
        ->and(CalendarConflict::query()->sole())->kept->toBe('tool')
        ->and(CalendarConflict::query()->sole()->losing['title'])->toBe('Showing — 2218 Elm St (REOS)');

    $this->actingAs($dana)->postJson('/api/v1/calendar/conflicts/'.CalendarConflict::query()->sole()->id.'/undo')->assertOk()
        ->assertJsonPath('data.title', 'Showing — 2218 Elm St (REOS)');
    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->hasHeader('If-Match', 'W/"DwAAABYAAABS/NhmmNy2TqjuB2zQxIpXAAAAAA5555"'));
});

it('subscribes per calendar with a signed client state, answers Graph\'s handshake, and accepts only genuine notifications', function () {
    URL::forceRootUrl('https://app.reos.test');
    URL::forceScheme('https');
    $state = [];
    fakeOutlook($state);
    $connection = connectOutlook(User::factory()->create())->fresh();

    $subscription = $state['subscriptions'][0];
    expect($subscription)->toMatchArray([
        'changeType' => 'created,updated,deleted',
        'resource' => 'me/events',
        'notificationUrl' => 'https://app.reos.test/webhooks/outlook-calendar',
        'lifecycleNotificationUrl' => 'https://app.reos.test/webhooks/outlook-calendar',
        'expirationDateTime' => '2026-11-27T15:00:00Z',
    ])->and(strlen((string) $subscription['clientState']))->toBeLessThanOrEqual(128)
        ->and(OutlookCalendarChannels::connectionFor((string) $subscription['clientState']))->toBe($connection?->id)
        ->and($connection?->meta['channels'][0])->toMatchArray(['resource' => 'primary', 'id' => '7f105c7d-2dc5-4530-97cd-4e7ae6534c07']);

    // The handshake: the token back, as plain text.
    $this->call('POST', '/webhooks/outlook-calendar?validationToken='.rawurlencode('Validation: Testing client application reachability for subscription Request-Id: 25'))
        ->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertContent('Validation: Testing client application reachability for subscription Request-Id: 25');

    Queue::fake();
    $notify = fn (string $clientState, array $extra = []) => $this->postJson('/webhooks/outlook-calendar', ['value' => [[
        'subscriptionId' => '7f105c7d-2dc5-4530-97cd-4e7ae6534c07', 'clientState' => $clientState, 'changeType' => 'updated',
        'resource' => 'Users/87d349ed/Events/AAMkAGI2TG93AAA=', ...$extra,
    ]]]);

    $notify((string) $subscription['clientState'])->assertStatus(202);
    $notify((string) $subscription['clientState'])->assertOk();
    $notify('reos-1-forged.'.str_repeat('0', 64))->assertStatus(401);
    Queue::assertPushed(SyncConnection::class, 1);

    // Graph ended the subscription: REOS opens a new one after catching up.
    $notify((string) $subscription['clientState'], ['lifecycleEvent' => 'subscriptionRemoved'])->assertStatus(202);
    expect(Connection::query()->sole()->meta['channels'])->toBe([]);
});

it('forgets the account on disconnect (Microsoft has no refresh-token revoke)', function () {
    $state = [];
    fakeOutlook($state);
    $dana = User::factory()->create();
    $connection = connectOutlook($dana);

    app(Connections::class)->disconnect($connection, $dana);

    expect(Connection::query()->exists())->toBeFalse();
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'revoke'));
});

it('asks to reconnect when Microsoft refuses the refresh', function () {
    $state = [
        'delta' => [Http::response(['error' => ['code' => 'InvalidAuthenticationToken', 'message' => 'Access token has expired.']], 401)],
        'token' => Http::sequence()
            ->push(outlookJson('token'))
            ->push(['error' => 'invalid_grant', 'error_description' => 'AADSTS70000: The refresh token has expired.'], 400),
    ];
    fakeOutlook($state);
    Queue::fake();
    connectOutlook(User::factory()->create());

    expect(app(SyncEngine::class)->run(Connection::query()->sole(), 'events', SyncTrigger::First))->toBe('failed')
        ->and(Connection::query()->sole()->status)->toBe(ConnectionStatus::ReconnectNeeded);
});

it('keeps the Family calendar busy only when chosen', function () {
    $state = ['delta' => [Http::response(outlookJson('delta-page-2')), Http::response(outlookJson('delta-page-2')), Http::response(['@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/calendars/x/calendarView/delta?$deltatoken=f', 'value' => [[
        'id' => 'fam1', 'subject' => 'Soccer practice', 'showAs' => 'busy', 'isAllDay' => false, 'lastModifiedDateTime' => '2026-11-20T10:00:00Z',
        'start' => ['dateTime' => '2026-11-22T23:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-11-23T00:30:00.0000000', 'timeZone' => 'UTC'],
        'location' => ['displayName' => 'Memorial Park'], 'attendees' => [['type' => 'required', 'emailAddress' => ['address' => 'coach@example.com']]],
    ]]])]];
    fakeOutlook($state);
    $connection = connectOutlook(User::factory()->create());

    app(CalendarConnections::class)->choose($connection->fresh(), ['AAMkAGI2TGuLCCC=' => 'busy'], null);

    expect(CalendarEvent::query()->where('calendar_id', 'AAMkAGI2TGuLCCC=')->sole())->busy->toBeTrue()->title->toBeNull()->location->toBeNull()->attendees->toBe([]);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/me/calendars/AAMkAGI2TGuLCCC%3D/calendarView/delta'));
});
