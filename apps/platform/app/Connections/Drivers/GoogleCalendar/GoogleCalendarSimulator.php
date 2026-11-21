<?php

declare(strict_types=1);

namespace App\Connections\Drivers\GoogleCalendar;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\SimulatorSignature;
use App\Connections\Stores\CalendarEventStore;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Google Calendar on demo data (ADR 0009 "Simulator" mode). It runs the same
 * path as the real tool: a sign-in page, a PKCE-checked code, tokens that
 * expire, paged sync with cursors, signed webhooks, and revocation (set
 * `meta.simulator_revoked` with `reos:connections:simulate`). The events
 * are a Texas agent's week: showings, a closing, a CE class, a lender call.
 */
final readonly class GoogleCalendarSimulator implements ConnectorDriver
{
    private const PAGE_SIZE = 5;

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
        if (($connection->meta['simulator_revoked'] ?? false) === true || SimulatorSignature::open($accessToken) === null) {
            throw new AccessRevoked('Simulator revoked the connection.');
        }

        $state = $cursor === null ? [] : (array) json_decode($cursor, true);

        // Incremental sync: the demo calendar has no new changes.
        if (isset($state['sync']) && ! isset($state['page'])) {
            return new SyncPage([], [], json_encode(['sync' => CarbonImmutable::now()->toIso8601String()], JSON_THROW_ON_ERROR), true);
        }

        $events = array_values(array_filter(
            $this->week($connection),
            static fn (array $event): bool => $since === null || $event['ends_at'] >= $since,
        ));
        $page = is_int($state['page'] ?? null) ? $state['page'] : 0;
        $slice = array_slice($events, $page * self::PAGE_SIZE, self::PAGE_SIZE);
        $more = count($events) > ($page + 1) * self::PAGE_SIZE;

        return new SyncPage(
            $slice,
            [],
            json_encode($more ? ['page' => $page + 1] : ['sync' => CarbonImmutable::now()->toIso8601String()], JSON_THROW_ON_ERROR),
            ! $more,
        );
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

    /** @param  array<string, mixed>  $claims */
    private static function accountOf(array $claims): string
    {
        return is_string($claims['account'] ?? null) && $claims['account'] !== '' ? $claims['account'] : 'demo@example.com';
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

    /**
     * A believable fortnight around today, the same for the same connection.
     *
     * @return list<array<string, mixed>>
     */
    private function week(Connection $connection): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $plan = [
            [-9, 10, 60, 'Listing appointment — 1402 Heights Blvd', '1402 Heights Blvd, Houston, TX'],
            [-6, 14, 90, 'Closing — Johnson family', 'Lone Star Title, 5 Greenway Plaza, Houston, TX'],
            [-2, 9, 30, 'Call with lender Priya Shah', null],
            [0, 11, 45, 'Showing — 2218 Elm St', '2218 Elm St, Houston, TX'],
            [0, 15, 60, 'Buyer consultation — the Nguyens', 'Lone Star Realty, Katy office'],
            [1, 10, 180, 'CE class: Legal Update I', 'Online'],
            [2, 13, 30, 'Inspection — 405 Westheimer Rd', '405 Westheimer Rd, Houston, TX'],
            [3, 16, 120, 'Open house — 77 Memorial Dr', '77 Memorial Dr, Houston, TX'],
            [5, 9, 60, 'Team meeting', 'Lone Star Realty, Katy office'],
            [8, 11, 45, 'Final walk-through — 2218 Elm St', '2218 Elm St, Houston, TX'],
            [10, 14, 90, 'Closing — 2218 Elm St', 'Lone Star Title, 5 Greenway Plaza, Houston, TX'],
            [12, 0, 0, 'Texas REALTORS® conference', 'Austin, TX'],
        ];

        return array_map(static function (array $row, int $index) use ($today, $connection): array {
            [$day, $hour, $minutes, $title, $location] = $row;
            $allDay = $minutes === 0;
            $start = $today->addDays($day)->addHours($hour);

            return [
                'external_id' => "sim-{$connection->id}-{$index}",
                'calendar_id' => 'primary',
                'title' => $title,
                'location' => $location,
                'starts_at' => $start->utc(),
                'ends_at' => ($allDay ? $start->addDay() : $start->addMinutes($minutes))->utc(),
                'all_day' => $allDay,
                'status' => 'confirmed',
                'html_link' => null,
                'remote_updated_at' => $today->subDays(14)->utc(),
            ];
        }, $plan, array_keys($plan));
    }
}
