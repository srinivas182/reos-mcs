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
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\RemoteChanged;
use App\Connections\SimulatorSignature;
use App\Connections\Stores\CalendarEventStore;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use App\Models\SimulatorRecord;
use App\Support\PersonTimezone;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Google Calendar on demo data (ADR 0009 "Simulator" mode), two-way (B4.1).
 *
 * It runs the same path as the real tool: a sign-in page, a PKCE-checked
 * code, tokens that expire, calendars to choose from, paged sync with sync
 * tokens per calendar, writes that check the version, signed webhooks, and
 * revocation (`reos:connections:simulate --revoke`).
 *
 * "Google" keeps its copy in `simulator_records`, seeded with a Texas
 * agent's fortnight: showings, a closing, a CE class, a lender call, plus a
 * personal and a family calendar. `changeInTool()` edits it as if the agent
 * did it in Google (`reos:connections:simulate --change-event`).
 */
final readonly class GoogleCalendarSimulator implements CalendarDriver, ConnectorDriver
{
    private const PAGE_SIZE = 5;

    private const STREAM = 'events';

    public function integration(): string
    {
        return GoogleCalendarDriver::INTEGRATION;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return route('connections.simulator', [
            'tool' => $this->integration(),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'redirect_uri' => $redirectUri,
        ]);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        $claims = SimulatorSignature::open($code);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        // The same PKCE check the real tool makes.
        if ($claims === null || ! is_string($claims['challenge'] ?? null) || ! hash_equals($claims['challenge'], $challenge) || ($claims['redirect_uri'] ?? null) !== $redirectUri) {
            throw new AccessRevoked('Simulator refused the code.');
        }

        return $this->tokens(self::accountOf($claims));
    }

    public function refresh(string $refreshToken): TokenSet
    {
        $claims = SimulatorSignature::open($refreshToken);

        if ($claims === null) {
            throw new AccessRevoked('Simulator refused the refresh token.');
        }

        return $this->tokens(self::accountOf($claims));
    }

    public function revoke(string $token): void {}

    public function account(TokenSet $tokens): ConnectedAccount
    {
        $account = self::accountOf(SimulatorSignature::open($tokens->accessToken) ?? []);

        return new ConnectedAccount('sim-'.substr(hash('sha256', $account), 0, 16), $account);
    }

    public function streams(): array
    {
        return ['events' => CalendarEventStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $this->authorise($connection, $accessToken);
        $state = GoogleCalendarCursor::open($cursor, CalendarChoices::synced($connection));
        $calendar = $state->calendar();

        if ($calendar === null) {
            return new SyncPage([], [], $state->encode(), true);
        }

        $token = $state->syncToken($calendar);
        $query = SimulatorRecord::query()->where('connection_id', $connection->id)->where('stream', self::STREAM)->where('container', $calendar);

        if ($token !== null) {
            // Changes since the last read; the same second again is harmless (versions match).
            $query->where('updated_at', '>=', CarbonImmutable::parse($token));
        } else {
            $state->since ??= ($since ?? $connection->history()->since())?->toIso8601String();
            $query->where('deleted', false);
            if ($state->since !== null) {
                $query->where('data->ends_at', '>=', CarbonImmutable::parse($state->since)->utc()->toIso8601String());
            }
        }

        $offset = (int) ($state->page ?? 0);
        $rows = $query->orderBy('id')->skip($offset)->take(self::PAGE_SIZE + 1)->get();
        $mode = CalendarChoices::mode($connection, $calendar);
        $records = [];
        $removed = [];

        foreach ($rows->take(self::PAGE_SIZE) as $row) {
            if ($row->deleted) {
                $removed[] = CalendarEventStore::key($calendar, $row->external_id);
            } else {
                $records[] = self::record($row, $mode);
            }
        }

        if ($rows->count() > self::PAGE_SIZE) {
            return new SyncPage($records, $removed, $state->nextPage((string) ($offset + self::PAGE_SIZE))->encode(), false);
        }

        $state->finishCalendar(Carbon::now()->toIso8601String());

        return new SyncPage($records, $removed, $state->encode(), $state->calendar() === null);
    }

    public function requestsPerMinute(): int
    {
        return 600;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        $signature = $request->header('X-Reos-Simulator-Signature');
        $body = $request->getContent();

        if (! is_string($signature) || ! hash_equals(SimulatorSignature::sign($body), $signature)) {
            return null;
        }

        $payload = (array) json_decode($body, true);
        $connection = $payload['connection_id'] ?? null;
        $delivery = $payload['delivery_id'] ?? null;

        if (! is_int($connection) || ! is_string($delivery)) {
            return null;
        }

        return new WebhookDelivery($delivery, [$connection]);
    }

    public function calendars(Connection $connection, string $accessToken): array
    {
        $this->authorise($connection, $accessToken);

        return [
            new CalendarInfo(CalendarChoices::PRIMARY, $connection->account_label ?? 'demo@example.com', true, true),
            new CalendarInfo('family', 'Family', false, true),
            new CalendarInfo('personal', 'Personal', false, true),
            new CalendarInfo('holidays', 'Holidays in United States', false, false),
        ];
    }

    public function insertEvent(Connection $connection, string $accessToken, string $calendarId, EventDraft $event): array
    {
        $this->authorise($connection, $accessToken);
        $this->writable($calendarId);
        $id = GoogleCalendarDriver::eventId($event);
        $existing = $this->find($connection, $calendarId, $id);

        // The same write twice finds the first (Google answers 409).
        if ($existing !== null) {
            return self::record($existing, CalendarMode::Full);
        }

        $row = SimulatorRecord::query()->create([
            'connection_id' => $connection->id,
            'stream' => self::STREAM,
            'container' => $calendarId,
            'external_id' => $id,
            'data' => [...self::data($event), 'reos_id' => $event->reosId],
            'etag' => self::etag(),
        ]);

        return self::record($row, CalendarMode::Full);
    }

    public function updateEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId, EventDraft $event, ?string $etag): array
    {
        $this->authorise($connection, $accessToken);
        $this->writable($calendarId);
        $row = $this->find($connection, $calendarId, $eventId);

        if ($row === null || $row->deleted || ($etag !== null && $etag !== $row->etag)) {
            throw new RemoteChanged('Changed in the simulated Google Calendar.');
        }

        $row->forceFill(['data' => [...$row->data, ...self::data($event)], 'etag' => self::etag()])->save();

        return self::record($row, CalendarMode::Full);
    }

    public function getEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId): ?array
    {
        $this->authorise($connection, $accessToken);
        $row = $this->find($connection, $calendarId, $eventId);

        return $row === null || $row->deleted ? null : self::record($row, CalendarChoices::mode($connection, $calendarId));
    }

    public function deleteEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId): void
    {
        $this->authorise($connection, $accessToken);
        $this->find($connection, $calendarId, $eventId)?->forceFill(['deleted' => true, 'etag' => self::etag()])->save();
    }

    /**
     * Change an event as if the person did it in Google: the next event on
     * the main calendar, or the one given. Returns the changed record.
     *
     * @param  array<string, mixed>  $changes  Fields as REOS stores them; `deleted` removes it.
     * @return array<string, mixed>|null
     */
    public static function changeInTool(Connection $connection, array $changes, ?string $eventId = null, string $calendarId = CalendarChoices::PRIMARY): ?array
    {
        self::seed($connection);
        $query = SimulatorRecord::query()->where('connection_id', $connection->id)->where('stream', self::STREAM)->where('container', $calendarId)->where('deleted', false);
        $row = $eventId !== null
            ? $query->where('external_id', $eventId)->first()
            : $query->where('data->starts_at', '>=', CarbonImmutable::now()->utc()->toIso8601String())->orderBy('data->starts_at')->first();

        if ($row === null) {
            return null;
        }

        $deleted = ($changes['deleted'] ?? false) === true;
        $changes = array_map(static fn (mixed $value): mixed => $value instanceof DateTimeInterface ? CarbonImmutable::parse($value)->utc()->toIso8601String() : $value, $changes);
        unset($changes['deleted']);
        $row->forceFill([
            'data' => [...$row->data, ...$changes, 'updated' => CarbonImmutable::now()->utc()->toIso8601String()],
            'etag' => self::etag(),
            'deleted' => $deleted,
        ])->save();

        return self::record($row, CalendarMode::Full);
    }

    /** Fill "Google" with the demo fortnight the first time a connection reads it. */
    public static function seed(Connection $connection): void
    {
        if (SimulatorRecord::query()->where('connection_id', $connection->id)->where('stream', self::STREAM)->exists()) {
            return;
        }

        // A Houston agent's day: times are Texas times.
        $today = CarbonImmutable::now(PersonTimezone::DEFAULT)->startOfDay();
        $plan = [
            ['primary', -9, 10, 60, 'Listing appointment — 1402 Heights Blvd', '1402 Heights Blvd, Houston, TX'],
            ['primary', -6, 14, 90, 'Closing — Johnson family', 'Lone Star Title, 5 Greenway Plaza, Houston, TX'],
            ['primary', -2, 9, 30, 'Call with lender Priya Shah', null],
            ['primary', 0, 11, 45, 'Showing — 2218 Elm St', '2218 Elm St, Houston, TX'],
            ['primary', 0, 15, 60, 'Buyer consultation — the Nguyens', 'Lone Star Realty, Katy office'],
            ['primary', 1, 10, 180, 'CE class: Legal Update I', 'Online'],
            ['primary', 2, 13, 30, 'Inspection — 405 Westheimer Rd', '405 Westheimer Rd, Houston, TX'],
            ['primary', 3, 16, 120, 'Open house — 77 Memorial Dr', '77 Memorial Dr, Houston, TX'],
            ['primary', 5, 9, 60, 'Team meeting', 'Lone Star Realty, Katy office'],
            ['primary', 8, 11, 45, 'Final walk-through — 2218 Elm St', '2218 Elm St, Houston, TX'],
            ['primary', 10, 14, 90, 'Closing — 2218 Elm St', 'Lone Star Title, 5 Greenway Plaza, Houston, TX'],
            ['primary', 12, 0, 0, 'Texas REALTORS® conference', 'Austin, TX'],
            ['personal', 0, 18, 60, 'Dentist', 'Heights Dental, Houston, TX'],
            ['personal', 2, 7, 60, 'Gym', null],
            ['family', 1, 17, 90, 'Soccer practice', 'Memorial Park, Houston, TX'],
            ['holidays', 6, 0, 0, 'Thanksgiving Day', null],
        ];

        foreach ($plan as $index => [$calendar, $day, $hour, $minutes, $title, $location]) {
            $allDay = $minutes === 0;
            // All-day events are dates: midnight UTC on that day, as Google stores them.
            $start = $allDay ? CarbonImmutable::parse($today->addDays($day)->toDateString(), 'UTC') : $today->addDays($day)->addHours($hour);
            $row = SimulatorRecord::query()->create([
                'connection_id' => $connection->id,
                'stream' => self::STREAM,
                'container' => $calendar,
                'external_id' => "sim-{$connection->id}-{$index}",
                'data' => [
                    'title' => $title,
                    'location' => $location,
                    'description' => null,
                    'attendees' => [],
                    'starts_at' => $start->utc()->toIso8601String(),
                    'ends_at' => ($allDay ? $start->addDay() : $start->addMinutes($minutes))->utc()->toIso8601String(),
                    'all_day' => $allDay,
                    'updated' => $today->subDays(14)->utc()->toIso8601String(),
                ],
                'etag' => self::etag(),
            ]);
            // Seeded "two weeks ago", so a first sync's sync token is later than all of them.
            $row->forceFill(['created_at' => $today->subDays(14), 'updated_at' => $today->subDays(14)])->saveQuietly();
        }
    }

    private function authorise(Connection $connection, string $accessToken): void
    {
        if (($connection->meta['simulator_revoked'] ?? false) === true || SimulatorSignature::open($accessToken) === null) {
            throw new AccessRevoked('Simulator revoked the connection.');
        }

        self::seed($connection);
    }

    private function writable(string $calendarId): void
    {
        if ($calendarId === 'holidays') {
            throw new AccessRevoked('The holidays calendar is read only.');
        }
    }

    private function find(Connection $connection, string $calendarId, string $eventId): ?SimulatorRecord
    {
        return SimulatorRecord::query()
            ->where('connection_id', $connection->id)->where('stream', self::STREAM)
            ->where('container', $calendarId)->where('external_id', $eventId)
            ->first();
    }

    /** @return array<string, mixed> */
    private static function data(EventDraft $event): array
    {
        return [
            'title' => $event->title,
            'location' => $event->location,
            'description' => $event->description,
            'attendees' => $event->attendees,
            'starts_at' => $event->startsAt->toIso8601String(),
            'ends_at' => $event->endsAt->toIso8601String(),
            'all_day' => $event->allDay,
            'updated' => CarbonImmutable::now()->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private static function record(SimulatorRecord $row, CalendarMode $mode): array
    {
        $data = $row->data;
        $busy = $mode === CalendarMode::Busy;
        $text = static fn (string $key): ?string => ! $busy && is_string($data[$key] ?? null) ? $data[$key] : null;
        $reosId = $data['reos_id'] ?? null;

        return [
            'external_id' => $row->external_id,
            'calendar_id' => $row->container,
            'title' => $text('title'),
            'location' => $text('location'),
            'description' => $text('description'),
            'attendees' => $busy ? [] : array_values(array_filter((array) ($data['attendees'] ?? []), is_string(...))),
            'starts_at' => CarbonImmutable::parse(is_string($data['starts_at'] ?? null) ? $data['starts_at'] : 'now')->utc(),
            'ends_at' => CarbonImmutable::parse(is_string($data['ends_at'] ?? null) ? $data['ends_at'] : 'now')->utc(),
            'all_day' => ($data['all_day'] ?? false) === true,
            'busy' => $busy,
            'status' => 'confirmed',
            'html_link' => null,
            'etag' => $row->etag,
            'remote_updated_at' => CarbonImmutable::parse(is_string($data['updated'] ?? null) ? $data['updated'] : 'now')->utc(),
            'reos_id' => is_int($reosId) ? $reosId : null,
        ];
    }

    /** @param  array<string, mixed>  $claims */
    private static function accountOf(array $claims): string
    {
        return is_string($claims['account'] ?? null) && $claims['account'] !== '' ? $claims['account'] : 'demo@example.com';
    }

    private static function etag(): string
    {
        return '"'.Str::random(16).'"';
    }

    private function tokens(string $account): TokenSet
    {
        return new TokenSet(
            SimulatorSignature::seal(['account' => $account, 'kind' => 'access', 'nonce' => Str::random(8)]),
            SimulatorSignature::seal(['account' => $account, 'kind' => 'refresh']),
            CarbonImmutable::now()->addHour(),
            GoogleCalendarDriver::SCOPES,
        );
    }
}
