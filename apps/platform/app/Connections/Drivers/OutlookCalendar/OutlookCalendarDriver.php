<?php

declare(strict_types=1);

namespace App\Connections\Drivers\OutlookCalendar;

use App\Connections\AnswersValidation;
use App\Connections\Calendar\CalendarChoices;
use App\Connections\Calendar\CalendarCursor;
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
 * Outlook Calendar (Microsoft 365 and Outlook.com), two-way, through
 * Microsoft Graph (B4.2, ADR 0017), on the `outlook-calendar` keys.
 *
 * - Sign-in: the Microsoft identity platform's "common" endpoint (work and
 *   personal accounts) with PKCE; `Calendars.ReadWrite`, `User.Read` and
 *   `offline_access`. Microsoft has no endpoint to revoke a refresh token,
 *   so disconnecting deletes REOS's copy and the person can remove REOS
 *   from their account's app permissions.
 * - Sync: a calendar-view delta query per chosen calendar, from the history
 *   start to two years ahead. Graph hands back an `@odata.deltaLink` per
 *   calendar for changes only; a 410 means it expired and every calendar
 *   starts again. Times are asked for in UTC.
 * - Write: events REOS adds carry a `transactionId` derived from REOS's own
 *   id and version, so Graph returns the first one when a retry repeats it.
 *   Changes send `If-Match` with the version REOS saw.
 * - Guests: Outlook emails everyone an event lists, so REOS adds guests only
 *   when the person asks for invitations; otherwise they stay in REOS.
 * - Push: a change-notification subscription per calendar (up to seven
 *   days, renewed a day early), with lifecycle notifications; REOS answers
 *   Graph's validation handshake and checks the signed `clientState`.
 *
 * @see https://learn.microsoft.com/en-us/graph/delta-query-events
 * @see https://learn.microsoft.com/en-us/graph/change-notifications-delivery-webhooks
 */
final readonly class OutlookCalendarDriver implements AnswersValidation, CalendarDriver, ConnectorDriver, SubscribesToChanges
{
    public const INTEGRATION = 'outlook-calendar';

    public const SCOPES = ['openid', 'email', 'offline_access', 'User.Read', 'Calendars.ReadWrite'];

    /** What the consent sheet promises (Connections and Sync journey, screen 5). */
    public const CONSENT = ['can' => ['read_events', 'write_events', 'change_alerts'], 'never' => ['delete_yours', 'email']];

    public const STREAMS = ['events' => 'both'];

    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    /** How far ahead the calendar view reaches. */
    private const AHEAD_YEARS = 2;

    /** Graph allows at most 10,080 minutes for Outlook events; REOS asks for six days and renews a day early. */
    private const SUBSCRIPTION_MINUTES = 8640;

    private OAuth2Client $oauth;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->oauth = new OAuth2Client(
            $clientId,
            $clientSecret,
            'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            null,
            self::SCOPES,
            ['prompt' => 'select_account', 'response_mode' => 'query'],
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
        $response = $this->send(fn (PendingRequest $http) => $http->get(self::GRAPH.'/me', ['$select' => 'id,mail,userPrincipalName']), $tokens->accessToken);
        $id = $response->json('id');
        $mail = $response->json('mail') ?? $response->json('userPrincipalName');

        if (! is_string($id) || $id === '') {
            throw new VendorUnavailable('Microsoft returned no account id.');
        }

        return new ConnectedAccount($id, is_string($mail) ? $mail : null);
    }

    public function streams(): array
    {
        return ['events' => CalendarEventStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $state = CalendarCursor::open($cursor, CalendarChoices::synced($connection));

        if ($state->calendar() === null) {
            return new SyncPage([], [], $state->encode(), true);
        }

        $calendar = (string) $state->calendar();

        // Graph's links carry the window and the place; only the first page names them.
        $url = $state->page ?? $state->syncToken($calendar);
        if ($url === null || ! str_starts_with($url, self::GRAPH.'/')) {
            $from = $since ?? $connection->history()->since() ?? CarbonImmutable::now()->subYears(10);
            $url = self::GRAPH.$this->calendarPath($calendar).'/calendarView/delta?'.http_build_query([
                'startDateTime' => $from->utc()->format('Y-m-d\TH:i:s\Z'),
                'endDateTime' => CarbonImmutable::now()->addYears(self::AHEAD_YEARS)->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);
        }

        $response = $this->send(fn (PendingRequest $http) => $http->withHeaders(['Prefer' => 'odata.maxpagesize=100, outlook.timezone="UTC"'])->get($url), $accessToken);
        $mode = CalendarChoices::mode($connection, $calendar);
        $records = [];
        $removed = [];

        foreach ((array) $response->json('value', []) as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                continue;
            }

            if (isset($item['@removed']) || ($item['isCancelled'] ?? false) === true || ($mode === CalendarMode::Busy && ($item['showAs'] ?? null) === 'free')) {
                $removed[] = CalendarEventStore::key($calendar, $item['id']);

                continue;
            }

            $record = self::record($item, $calendar, $mode);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        // Graph's keys contain dots, which `json('…')` would read as a path.
        $page = (array) $response->json();
        $next = $page['@odata.nextLink'] ?? null;
        $delta = $page['@odata.deltaLink'] ?? null;

        if (is_string($next) && $next !== '') {
            return new SyncPage($records, $removed, $state->nextPage($next)->encode(), false);
        }

        $state->finishCalendar(is_string($delta) ? $delta : null);

        return new SyncPage($records, $removed, $state->encode(), $state->calendar() === null);
    }

    public function requestsPerMinute(): int
    {
        // Well under Graph's per-mailbox limit (10,000 requests in 10 minutes).
        return 300;
    }

    public function validationAnswer(Request $request): ?string
    {
        $token = $request->query('validationToken');

        // Graph's tokens are opaque text; anything long or odd is not Graph.
        return is_string($token) && $token !== '' && strlen($token) <= 1024 && preg_match('/^[\x20-\x7E]+$/', $token) === 1 ? $token : null;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        $body = $request->getContent();
        $notifications = (array) ($request->json('value') ?? []);
        $connections = [];
        $lost = false;

        foreach ($notifications as $notification) {
            if (! is_array($notification) || ! is_string($notification['clientState'] ?? null)) {
                continue;
            }

            $connectionId = OutlookCalendarChannels::connectionFor($notification['clientState']);

            if ($connectionId === null) {
                continue;
            }

            $connections[] = $connectionId;
            // reauthorizationRequired, subscriptionRemoved, missed: open the channel again and catch up.
            $lost = $lost || in_array($notification['lifecycleEvent'] ?? null, ['reauthorizationRequired', 'subscriptionRemoved'], true);
        }

        if ($connections === []) {
            return null;
        }

        return new WebhookDelivery('graph:'.hash('sha256', $body), array_values(array_unique($connections)), true, $lost);
    }

    public function watchedResources(Connection $connection): array
    {
        return CalendarChoices::synced($connection);
    }

    public function subscribe(Connection $connection, string $accessToken, string $webhookUrl, string $resource): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post(self::GRAPH.'/subscriptions', [
            'changeType' => 'created,updated,deleted',
            'notificationUrl' => $webhookUrl,
            'lifecycleNotificationUrl' => $webhookUrl,
            // The main calendar's own id isn't known here; "me/events" covers it (and nudges a sync for others).
            'resource' => $resource === CalendarChoices::PRIMARY ? 'me/events' : 'me/calendars/'.$resource.'/events',
            'expirationDateTime' => CarbonImmutable::now()->addMinutes(self::SUBSCRIPTION_MINUTES)->utc()->format('Y-m-d\TH:i:s\Z'),
            'clientState' => OutlookCalendarChannels::clientState($connection->id),
        ]), $accessToken);
        $id = $response->json('id');
        $expires = $response->json('expirationDateTime');

        if (! is_string($id) || $id === '') {
            throw new VendorUnavailable('Microsoft returned no subscription id.');
        }

        return [
            'id' => $id,
            'resource_id' => null,
            'expires_at' => is_string($expires) ? CarbonImmutable::parse($expires)->toIso8601String() : null,
        ];
    }

    public function unsubscribe(Connection $connection, string $accessToken, string $channelId, ?string $resourceId): void
    {
        $this->send(fn (PendingRequest $http) => $http->delete(self::GRAPH.'/subscriptions/'.rawurlencode($channelId)), $accessToken, [404]);
    }

    public function calendars(Connection $connection, string $accessToken): array
    {
        $calendars = [];
        $url = self::GRAPH.'/me/calendars?'.http_build_query(['$select' => 'id,name,isDefaultCalendar,canEdit', '$top' => '100']);

        // Four pages of 100 is more calendars than anyone has.
        for ($i = 0; $i < 4 && is_string($url); $i++) {
            $page = $url;
            $response = $this->send(fn (PendingRequest $http) => $http->get($page), $accessToken);

            foreach ((array) $response->json('value', []) as $item) {
                if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                    continue;
                }

                $primary = ($item['isDefaultCalendar'] ?? false) === true;
                $name = is_string($item['name'] ?? null) ? mb_substr($item['name'], 0, 120) : $item['id'];
                $calendars[] = new CalendarInfo($primary ? CalendarChoices::PRIMARY : $item['id'], $primary ? ($connection->account_label ?? $name) : $name, $primary, ($item['canEdit'] ?? false) === true);
            }

            $url = ((array) $response->json())['@odata.nextLink'] ?? null;
        }

        usort($calendars, static fn (CalendarInfo $a, CalendarInfo $b): int => [$b->primary, $a->name] <=> [$a->primary, $b->name]);

        return $calendars;
    }

    public function insertEvent(Connection $connection, string $accessToken, string $calendarId, EventDraft $event): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])->post(
            self::GRAPH.$this->calendarPath($calendarId).'/events',
            [...self::body($event), 'transactionId' => self::transactionId($event)],
        ), $accessToken);

        return self::record((array) $response->json(), $calendarId, CalendarMode::Full) ?? throw new VendorUnavailable('Microsoft returned an event without times.');
    }

    public function updateEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId, EventDraft $event, ?string $etag): array
    {
        $response = $this->send(
            fn (PendingRequest $http) => $http->withHeaders(['Prefer' => 'outlook.timezone="UTC"', ...($etag === null ? [] : ['If-Match' => $etag])])
                ->patch(self::GRAPH.'/me/events/'.rawurlencode($eventId), self::body($event)),
            $accessToken,
            [404, 412],
        );

        if (in_array($response->status(), [404, 412], true)) {
            throw new RemoteChanged("Microsoft answered {$response->status()}.");
        }

        return self::record((array) $response->json(), $calendarId, CalendarMode::Full) ?? throw new VendorUnavailable('Microsoft returned an event without times.');
    }

    public function getEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId): ?array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])->get(self::GRAPH.'/me/events/'.rawurlencode($eventId)), $accessToken, [404]);

        if ($response->status() === 404 || $response->json('isCancelled') === true) {
            return null;
        }

        return self::record((array) $response->json(), $calendarId, CalendarChoices::mode($connection, $calendarId));
    }

    public function deleteEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId): void
    {
        $this->send(fn (PendingRequest $http) => $http->delete(self::GRAPH.'/me/events/'.rawurlencode($eventId)), $accessToken, [404]);
    }

    /** The same REOS event and version always sends the same id, so Graph returns the first on a retry. */
    public static function transactionId(EventDraft $event): string
    {
        return 'reos-'.substr(hash_hmac('sha256', "outlook-calendar-event:{$event->key}", config()->string('app.key')), 0, 40);
    }

    private function calendarPath(string $calendarId): string
    {
        return $calendarId === CalendarChoices::PRIMARY ? '/me/calendar' : '/me/calendars/'.rawurlencode($calendarId);
    }

    /** @return array<string, mixed> */
    private static function body(EventDraft $event): array
    {
        // All-day events start and end at midnight in one time zone; REOS keeps them in UTC.
        $when = static fn (CarbonImmutable $at): array => ['dateTime' => $event->allDay ? $at->format('Y-m-d\T00:00:00') : $at->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'];

        return [
            'subject' => $event->title,
            'body' => ['contentType' => 'text', 'content' => $event->description ?? ''],
            'start' => $when($event->startsAt),
            'end' => $when($event->endsAt),
            'isAllDay' => $event->allDay,
            'location' => ['displayName' => $event->location ?? ''],
            // Outlook invites everyone listed: guests go there only when the person asked for invitations.
            'attendees' => $event->notifyGuests
                ? array_map(static fn (string $email): array => ['emailAddress' => ['address' => $email], 'type' => 'required'], $event->attendees)
                : [],
        ];
    }

    /**
     * One event as REOS stores it, or null without times. `attendees` is left
     * out when Outlook lists none, so guests kept only in REOS stay.
     *
     * @param  array<array-key, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function record(array $item, string $calendarId, CalendarMode $mode): ?array
    {
        $start = is_array($item['start'] ?? null) ? $item['start'] : [];
        $end = is_array($item['end'] ?? null) ? $item['end'] : [];
        $startsAt = $start['dateTime'] ?? null;
        $endsAt = $end['dateTime'] ?? $startsAt;

        if (! is_string($startsAt) || ! is_string($endsAt) || ! is_string($item['id'] ?? null)) {
            return null;
        }

        $busy = $mode === CalendarMode::Busy;
        $allDay = ($item['isAllDay'] ?? false) === true;
        // Asked for in UTC; Graph sends seven decimal places and no offset.
        $at = static fn (string $value): CarbonImmutable => CarbonImmutable::parse(substr($value, 0, 19), 'UTC');
        $location = is_array($item['location'] ?? null) && is_string($item['location']['displayName'] ?? null) ? $item['location']['displayName'] : '';
        $body = is_array($item['body'] ?? null) ? $item['body'] : [];
        $notes = is_string($body['content'] ?? null) ? $body['content'] : '';
        if (($body['contentType'] ?? null) === 'html') {
            $notes = trim(html_entity_decode(strip_tags((string) preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $notes)), ENT_QUOTES | ENT_HTML5));
        }

        $attendees = [];
        foreach ($busy ? [] : (array) ($item['attendees'] ?? []) as $attendee) {
            $email = is_array($attendee) && is_array($attendee['emailAddress'] ?? null) ? ($attendee['emailAddress']['address'] ?? null) : null;
            if (is_string($email) && $email !== '' && ($attendee['type'] ?? null) !== 'resource') {
                $attendees[] = mb_strtolower($email);
            }
        }

        $etag = $item['@odata.etag'] ?? $item['changeKey'] ?? null;
        $record = [
            'external_id' => $item['id'],
            'calendar_id' => $calendarId,
            'title' => $busy || ! is_string($item['subject'] ?? null) || $item['subject'] === '' ? null : mb_substr($item['subject'], 0, 255),
            'location' => $busy || $location === '' ? null : mb_substr($location, 0, 1000),
            'description' => $busy || $notes === '' ? null : mb_substr($notes, 0, 8000),
            'starts_at' => $allDay ? CarbonImmutable::parse(substr($startsAt, 0, 10), 'UTC') : $at($startsAt),
            'ends_at' => $allDay ? CarbonImmutable::parse(substr($endsAt, 0, 10), 'UTC') : $at($endsAt),
            'all_day' => $allDay,
            'busy' => $busy,
            'status' => 'confirmed',
            'html_link' => $busy || ! is_string($item['webLink'] ?? null) ? null : mb_substr($item['webLink'], 0, 2048),
            'etag' => is_string($etag) ? $etag : null,
            'remote_updated_at' => is_string($item['lastModifiedDateTime'] ?? null) ? CarbonImmutable::parse($item['lastModifiedDateTime'])->utc() : null,
            'reos_id' => null,
        ];

        if ($busy || $attendees !== []) {
            $record['attendees'] = $attendees;
        }

        return $record;
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
            throw new VendorUnavailable('Microsoft Graph unreachable.', previous: $e);
        }

        if (in_array($response->status(), $expected, true)) {
            return $response;
        }

        // An expired delta link (syncStateNotFound): read the calendar again.
        if ($response->status() === 410) {
            throw new CursorExpired('Microsoft delta link expired.');
        }

        OAuth2Client::raiseFor($response);

        return $response;
    }
}
