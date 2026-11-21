<?php

declare(strict_types=1);

namespace App\Connections\Drivers\GoogleCalendar;

use App\Connections\Calendar\CalendarChoices;
use App\Connections\Calendar\CalendarDriver;
use App\Connections\Calendar\CalendarInfo;
use App\Connections\Calendar\CalendarMode;
use App\Connections\Calendar\EventDraft;
use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Exceptions\CursorExpired;
use App\Connections\Exceptions\RemoteChanged;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\OAuth2Client;
use App\Connections\Stores\CalendarEventStore;
use App\Connections\SubscribesToChanges;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Google Calendar, two-way (B4.1, ADR 0017), on the `google-calendar` client
 * keys from Settings › Integrations.
 *
 * - Sign-in: Google OAuth with PKCE and offline access. Calendar only:
 *   `calendar.events` (read and write events) and
 *   `calendar.calendarlist.readonly` (the calendars to choose from).
 * - Sync: each calendar the person chose (`CalendarChoices`), one after the
 *   other. The first pass pages from the history start; Google then hands
 *   back a `nextSyncToken` per calendar for changes only. A 410 means a
 *   token expired and every calendar starts again. Calendars shared as "busy
 *   only" keep times and nothing else.
 * - Write: events REOS makes get an id derived from REOS's own, so a retry
 *   after a lost answer finds the event instead of adding it twice. Changes
 *   send `If-Match` with the version REOS saw; a 412 means it changed in
 *   Google meanwhile and the conflict rule decides.
 * - Push: one `events.watch` channel per synced calendar, verified by the
 *   channel token REOS set (`GoogleCalendarChannels`), renewed before it ends.
 *
 * @see https://developers.google.com/calendar/api/guides/sync
 * @see https://developers.google.com/calendar/api/guides/push
 * @see https://developers.google.com/calendar/api/guides/version-resources
 */
final readonly class GoogleCalendarDriver implements CalendarDriver, ConnectorDriver, SubscribesToChanges
{
    public const INTEGRATION = 'google-calendar';

    public const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
    ];

    /** What the consent sheet promises (Connections and Sync journey, screen 5). */
    public const CONSENT = ['can' => ['read_events', 'write_events', 'change_alerts'], 'never' => ['delete_yours', 'email']];

    /** Each stream and which way it flows (screens 6–7). */
    public const STREAMS = ['events' => 'both'];

    private const API = 'https://www.googleapis.com/calendar/v3';

    private OAuth2Client $oauth;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->oauth = new OAuth2Client(
            $clientId,
            $clientSecret,
            'https://accounts.google.com/o/oauth2/v2/auth',
            'https://oauth2.googleapis.com/token',
            'https://oauth2.googleapis.com/revoke',
            self::SCOPES,
            // A refresh token every time, and keep scopes granted earlier.
            ['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true'],
        );
    }

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return $this->oauth->authorizationUrl($state, $codeChallenge, $redirectUri);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        return $this->oauth->exchangeCode($code, $codeVerifier, $redirectUri);
    }

    public function refresh(string $refreshToken): TokenSet
    {
        return $this->oauth->refresh($refreshToken);
    }

    public function revoke(string $token): void
    {
        $this->oauth->revoke($token);
    }

    public function account(TokenSet $tokens): ConnectedAccount
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('https://openidconnect.googleapis.com/v1/userinfo'), $tokens->accessToken);
        $subject = $response->json('sub');
        $email = $response->json('email');

        if (! is_string($subject) || $subject === '') {
            throw new VendorUnavailable('Google returned no account id.');
        }

        return new ConnectedAccount($subject, is_string($email) ? $email : null);
    }

    public function streams(): array
    {
        return ['events' => CalendarEventStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $state = GoogleCalendarCursor::open($cursor, CalendarChoices::synced($connection));

        if ($state->calendar() === null) {
            return new SyncPage([], [], $state->encode(), true);
        }

        $calendar = (string) $state->calendar();
        $query = ['singleEvents' => 'true', 'showDeleted' => 'true', 'maxResults' => '250'];

        if ($state->page !== null) {
            $query['pageToken'] = $state->page;
        }
        if ($state->syncToken($calendar) !== null) {
            // Changes since the last sync; Google doesn't allow timeMin with a sync token.
            $query['syncToken'] = (string) $state->syncToken($calendar);
        } else {
            $state->since ??= ($since ?? $connection->history()->since())?->toRfc3339String();
            if ($state->since !== null) {
                $query['timeMin'] = $state->since;
            }
        }

        $response = $this->send(fn (PendingRequest $http) => $http->get(self::API.'/calendars/'.rawurlencode($calendar).'/events', $query), $accessToken);
        $mode = CalendarChoices::mode($connection, $calendar);
        $records = [];
        $removed = [];

        foreach ((array) $response->json('items', []) as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                continue;
            }

            // Cancelled, or marked "free" on a calendar shared as busy only.
            if (($item['status'] ?? null) === 'cancelled' || ($mode === CalendarMode::Busy && ($item['transparency'] ?? null) === 'transparent')) {
                $removed[] = CalendarEventStore::key($calendar, $item['id']);

                continue;
            }

            $record = self::record($item, $calendar, $mode);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        $nextPage = $response->json('nextPageToken');
        $nextSync = $response->json('nextSyncToken');

        if (is_string($nextPage) && $nextPage !== '') {
            return new SyncPage($records, $removed, $state->nextPage($nextPage)->encode(), false);
        }

        $state->finishCalendar(is_string($nextSync) ? $nextSync : null);

        return new SyncPage($records, $removed, $state->encode(), $state->calendar() === null);
    }

    public function requestsPerMinute(): int
    {
        // Well under Google Calendar's default per-project quota.
        return 300;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        $channel = $request->header('X-Goog-Channel-ID');
        $token = $request->header('X-Goog-Channel-Token');
        $number = $request->header('X-Goog-Message-Number');
        $state = $request->header('X-Goog-Resource-State');

        if (! is_string($channel) || ! is_string($token) || ! is_string($number) || ! is_string($state)) {
            return null;
        }

        $connectionId = GoogleCalendarChannels::connectionFor($channel, $token);

        if ($connectionId === null) {
            return null;
        }

        return new WebhookDelivery("{$channel}:{$number}", [$connectionId], $state !== 'sync');
    }

    public function watchedResources(Connection $connection): array
    {
        return CalendarChoices::synced($connection);
    }

    public function subscribe(Connection $connection, string $accessToken, string $webhookUrl, string $resource): array
    {
        $channel = GoogleCalendarChannels::newChannelId($connection->id);
        $response = $this->send(fn (PendingRequest $http) => $http->post(self::API.'/calendars/'.rawurlencode($resource).'/events/watch', [
            'id' => $channel,
            'type' => 'web_hook',
            'address' => $webhookUrl,
            'token' => GoogleCalendarChannels::token($channel),
        ]), $accessToken);
        $expiration = $response->json('expiration');
        $resourceId = $response->json('resourceId');

        return [
            'id' => $channel,
            'resource_id' => is_string($resourceId) ? $resourceId : null,
            // Google sends milliseconds; channels last about a week.
            'expires_at' => is_numeric($expiration) ? CarbonImmutable::createFromTimestampMs((int) $expiration)->toIso8601String() : null,
        ];
    }

    public function unsubscribe(Connection $connection, string $accessToken, string $channelId, ?string $resourceId): void
    {
        $this->send(fn (PendingRequest $http) => $http->post(self::API.'/channels/stop', array_filter(['id' => $channelId, 'resourceId' => $resourceId])), $accessToken, [404]);
    }

    public function calendars(Connection $connection, string $accessToken): array
    {
        $calendars = [];
        $page = null;

        // Four pages of 250 is more calendars than anyone subscribes to.
        for ($i = 0; $i < 4; $i++) {
            $response = $this->send(fn (PendingRequest $http) => $http->get(self::API.'/users/me/calendarList', array_filter([
                'maxResults' => '250',
                'pageToken' => $page,
            ])), $accessToken);

            foreach ((array) $response->json('items', []) as $item) {
                if (! is_array($item) || ! is_string($item['id'] ?? null) || ($item['deleted'] ?? false) === true) {
                    continue;
                }

                $primary = ($item['primary'] ?? false) === true;
                $name = $item['summaryOverride'] ?? $item['summary'] ?? $item['id'];
                $calendars[] = new CalendarInfo(
                    $primary ? CalendarChoices::PRIMARY : $item['id'],
                    is_string($name) ? mb_substr($name, 0, 120) : $item['id'],
                    $primary,
                    in_array($item['accessRole'] ?? null, ['owner', 'writer'], true),
                );
            }

            $page = $response->json('nextPageToken');

            if (! is_string($page) || $page === '') {
                break;
            }
        }

        // Main calendar first, then by name.
        usort($calendars, static fn (CalendarInfo $a, CalendarInfo $b): int => [$b->primary, $a->name] <=> [$a->primary, $b->name]);

        return $calendars;
    }

    public function insertEvent(Connection $connection, string $accessToken, string $calendarId, EventDraft $event): array
    {
        $id = self::eventId($event);
        $response = $this->send(
            fn (PendingRequest $http) => $http->post(self::API.'/calendars/'.rawurlencode($calendarId).'/events?'.http_build_query(['sendUpdates' => $event->notifyGuests ? 'all' : 'none']), [...self::body($event), 'id' => $id]),
            $accessToken,
            [409],
        );

        if ($response->status() === 409) {
            // Added already by an earlier try whose answer was lost.
            return $this->getEvent($connection, $accessToken, $calendarId, $id) ?? throw new VendorUnavailable('Google reported the event both added and missing.');
        }

        return self::record((array) $response->json(), $calendarId, CalendarMode::Full) ?? throw new VendorUnavailable('Google returned an event without times.');
    }

    public function updateEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId, EventDraft $event, ?string $etag): array
    {
        $response = $this->send(
            fn (PendingRequest $http) => $http->withHeaders($etag === null ? [] : ['If-Match' => $etag])
                ->patch(self::API.'/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId).'?'.http_build_query(['sendUpdates' => $event->notifyGuests ? 'all' : 'none']), self::body($event)),
            $accessToken,
            [404, 410, 412],
        );

        if (in_array($response->status(), [404, 410, 412], true)) {
            // Changed or deleted in Google since REOS last saw it.
            throw new RemoteChanged("Google answered {$response->status()}.");
        }

        return self::record((array) $response->json(), $calendarId, CalendarMode::Full) ?? throw new VendorUnavailable('Google returned an event without times.');
    }

    public function getEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId): ?array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get(self::API.'/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId)), $accessToken, [404, 410]);

        if (in_array($response->status(), [404, 410], true) || $response->json('status') === 'cancelled') {
            return null;
        }

        return self::record((array) $response->json(), $calendarId, CalendarChoices::mode($connection, $calendarId));
    }

    public function deleteEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId): void
    {
        $this->send(fn (PendingRequest $http) => $http->delete(self::API.'/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId).'?sendUpdates=none'), $accessToken, [404, 410]);
    }

    /**
     * Google event ids are 5–1024 characters of base32hex (0–9, a–v); hex
     * is a subset. The same REOS event and version always gets the same id.
     */
    public static function eventId(EventDraft $event): string
    {
        return 'reos'.substr(hash_hmac('sha256', "google-calendar-event:{$event->key}", config()->string('app.key')), 0, 32);
    }

    /** @return array<string, mixed> */
    private static function body(EventDraft $event): array
    {
        $when = static fn (CarbonImmutable $at): array => $event->allDay ? ['date' => $at->toDateString(), 'dateTime' => null, 'timeZone' => null] : ['dateTime' => $at->toRfc3339String(), 'date' => null, 'timeZone' => 'UTC'];

        return [
            'summary' => $event->title,
            'location' => $event->location,
            'description' => $event->description,
            'start' => $when($event->startsAt),
            'end' => $when($event->endsAt),
            'attendees' => array_map(static fn (string $email): array => ['email' => $email], $event->attendees),
            'extendedProperties' => ['private' => ['reos_event_id' => (string) $event->reosId]],
        ];
    }

    /**
     * One event as REOS stores it, or null when Google sends something without times.
     *
     * @param  array<array-key, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function record(array $item, string $calendarId, CalendarMode $mode): ?array
    {
        $start = is_array($item['start'] ?? null) ? $item['start'] : [];
        $end = is_array($item['end'] ?? null) ? $item['end'] : [];
        $allDay = isset($start['date']) && ! isset($start['dateTime']);
        $startsAt = $start['dateTime'] ?? $start['date'] ?? null;
        $endsAt = $end['dateTime'] ?? $end['date'] ?? $startsAt;

        if (! is_string($startsAt) || ! is_string($endsAt) || ! is_string($item['id'] ?? null)) {
            return null;
        }

        $busy = $mode === CalendarMode::Busy;
        $text = static fn (string $key, int $limit) => ! $busy && is_string($item[$key] ?? null) && $item[$key] !== '' ? mb_substr($item[$key], 0, $limit) : null;
        $attendees = [];

        foreach ($busy ? [] : (array) ($item['attendees'] ?? []) as $attendee) {
            // Not the person themself, and not rooms.
            if (is_array($attendee) && is_string($attendee['email'] ?? null) && ($attendee['self'] ?? false) !== true && ($attendee['resource'] ?? false) !== true) {
                $attendees[] = mb_strtolower($attendee['email']);
            }
        }

        $properties = is_array($item['extendedProperties'] ?? null) ? $item['extendedProperties'] : [];
        $private = is_array($properties['private'] ?? null) ? $properties['private'] : [];
        $reosId = $private['reos_event_id'] ?? null;

        return [
            'external_id' => $item['id'],
            'calendar_id' => $calendarId,
            'title' => $text('summary', 255),
            'location' => $text('location', 1000),
            'description' => $text('description', 8000),
            'attendees' => $attendees,
            'starts_at' => CarbonImmutable::parse($startsAt)->utc(),
            'ends_at' => CarbonImmutable::parse($endsAt)->utc(),
            'all_day' => $allDay,
            'busy' => $busy,
            'status' => is_string($item['status'] ?? null) ? $item['status'] : 'confirmed',
            'html_link' => $busy ? null : $text('htmlLink', 2048),
            'etag' => is_string($item['etag'] ?? null) ? $item['etag'] : null,
            'remote_updated_at' => is_string($item['updated'] ?? null) ? CarbonImmutable::parse($item['updated'])->utc() : null,
            'reos_id' => is_numeric($reosId) ? (int) $reosId : null,
        ];
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @param  list<int>  $expected  Statuses the caller handles itself.
     */
    private function send(callable $call, string $accessToken, array $expected = []): Response
    {
        try {
            $response = $call(Http::withToken($accessToken)->acceptJson()->asJson()->timeout(20));
        } catch (ConnectionException $e) {
            throw new VendorUnavailable('Google unreachable.', previous: $e);
        }

        if (in_array($response->status(), $expected, true)) {
            return $response;
        }

        if ($response->status() === 410) {
            throw new CursorExpired('Google sync token expired.');
        }

        OAuth2Client::raiseFor($response);

        return $response;
    }
}
