<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Dotloop;

use App\Connections\AcceptsDocuments;
use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Drivers\FollowUpBoss\WebhookTarget;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\SimulatorSignature;
use App\Connections\Stores\DealStore;
use App\Connections\SubscribesToChanges;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use App\Models\Deal;
use App\Models\SimulatorRecord;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * An agent's dotloop on demo data (B9.2): the demo sign-in page, two
 * profiles (the agent's own and a team's) with a loop each, with details,
 * participants, documents and a task list, in dotloop's own field names.
 * Webhooks are signed like dotloop's.
 */
final class DotloopSimulator implements AcceptsDocuments, ConnectorDriver, SubscribesToChanges
{
    private const CONTAINER = 'dl';

    public const PROFILES = [['id' => '4711', 'name' => 'Individual'], ['id' => '4712', 'name' => 'Lone Star Team']];

    public function integration(): string
    {
        return DotloopDriver::INTEGRATION;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return route('connections.simulator', ['tool' => $this->integration(), 'state' => $state, 'code_challenge' => $codeChallenge, 'redirect_uri' => $redirectUri]);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        $claims = SimulatorSignature::open($code);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        if ($claims === null || ! is_string($claims['challenge'] ?? null) || ! hash_equals($claims['challenge'], $challenge) || ($claims['redirect_uri'] ?? null) !== $redirectUri) {
            throw new AccessRevoked('Simulator refused the code.');
        }

        return $this->tokens();
    }

    public function refresh(string $refreshToken): TokenSet
    {
        return SimulatorSignature::open($refreshToken) === null ? throw new AccessRevoked('Simulator refused the refresh token.') : $this->tokens();
    }

    public function revoke(string $token): void {}

    public function account(TokenSet $tokens): ConnectedAccount
    {
        return new ConnectedAccount('dl-sim-1', 'agent@dotloop.example (demo)');
    }

    public function streams(): array
    {
        return ['deals' => DealStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        if (($connection->meta['simulator_revoked'] ?? false) === true) {
            throw new AccessRevoked('Simulator revoked the connection.');
        }
        self::seed($connection);
        DotloopProfiles::remember($connection, self::PROFILES);
        $chosen = DotloopProfiles::chosen($connection);
        $records = [];

        foreach (SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->where('stream', 'loops')->orderBy('id')->get() as $row) {
            $data = $row->data;
            if (! in_array($data['profileId'] ?? null, $chosen, true)) {
                continue;
            }
            $records[] = DotloopRecords::deal(
                [...$data, 'id' => $row->external_id, 'updated' => $row->updated_at?->toIso8601ZuluString()],
                self::list($data['participants'] ?? null),
                self::list($data['folders'] ?? null),
                self::list($data['tasks'] ?? null),
            );
        }

        return new SyncPage($records, [], null, true);
    }

    public function requestsPerMinute(): int
    {
        return 600;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        $connection = WebhookTarget::open($request->query('c'), 'dotloop-webhook');
        $signature = $request->header('X-DOTLOOP-WEBHOOK-SIGNATURE');
        $timestamp = $request->header('X-DOTLOOP-WEBHOOK-TIMESTAMP');

        if ($connection === null || ! is_string($signature) || ! is_string($timestamp) || ! hash_equals(hash_hmac('sha1', $timestamp.'.'.$request->getContent(), DotloopDriver::signingKey($connection)), $signature)) {
            return null;
        }

        $event = ((array) json_decode($request->getContent(), true))['eventId'] ?? null;

        return is_scalar($event) ? new WebhookDelivery('dotloop:'.$event, [$connection]) : null;
    }

    public function watchedResources(Connection $connection): array
    {
        return DotloopProfiles::chosen($connection);
    }

    public function subscribe(Connection $connection, string $accessToken, string $webhookUrl, string $resource): array
    {
        return ['id' => 'dl-sub-'.$resource.'-'.Str::lower(Str::random(6)), 'resource_id' => $resource, 'expires_at' => null];
    }

    public function unsubscribe(Connection $connection, string $accessToken, string $channelId, ?string $resourceId): void {}

    /** The signed copy lands in the loop's "Signed (REOS)" folder, and shows on the deal at the next sync. */
    public function uploadDocument(Connection $connection, string $accessToken, Deal $deal, string $name, string $contents): bool
    {
        $row = $deal->external_id === null ? null : SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->where('external_id', $deal->external_id)->first();

        if ($row === null || $contents === '') {
            return false;
        }

        $folders = self::list($row->data['folders'] ?? null);
        $at = array_find_key($folders, static fn (array $f): bool => ($f['name'] ?? null) === DotloopDriver::SIGNED_FOLDER);
        if ($at === null) {
            $folders[] = ['name' => DotloopDriver::SIGNED_FOLDER, 'documents' => []];
            $at = array_key_last($folders);
        }
        $folders[$at]['documents'] = [...self::list($folders[$at]['documents'] ?? null), ['name' => $name]];
        self::write($connection, $deal->external_id, ['folders' => $folders]);

        return true;
    }

    /**
     * Something changes in "dotloop" itself (demos and tests).
     *
     * @param  array<string, mixed>  $data  dotloop's own field names
     */
    public static function changeInTool(Connection $connection, string $id, array $data): SimulatorRecord
    {
        self::seed($connection);

        return self::write($connection, $id, $data);
    }

    public static function seed(Connection $connection): void
    {
        if (SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->exists()) {
            return;
        }

        $day = static fn (int $days): string => CarbonImmutable::now('America/Chicago')->addDays($days)->format('m/d/Y');
        self::write($connection, '90210', [
            'profileId' => '4711', 'name' => '1302 Live Oak St, Austin, TX 78704', 'transactionType' => 'PURCHASE_OFFER', 'status' => 'UNDER_CONTRACT',
            'loopUrl' => 'https://www.dotloop.com/my/loop/90210',
            'details' => [
                'Property Address' => ['Street Number' => '1302', 'Street Name' => 'Live Oak St', 'City' => 'Austin', 'State/Prov' => 'TX'],
                'Financials' => ['Purchase/Sale Price' => '475,000', 'Sale Commission Total' => '14,250'],
                'Contract Dates' => ['Contract Agreement Date' => $day(-5), 'Closing Date' => $day(28)],
                'Offer Dates' => ['Inspection Date' => $day(4)],
                'Contract Info' => ['Option Period Expiration Date' => $day(2)],
            ],
            'participants' => [['fullName' => 'Priya Shah', 'role' => 'BUYER', 'email' => 'priya.shah@example.com'], ['fullName' => 'Hill Country Title', 'role' => 'ESCROW_TITLE_REP']],
            'folders' => [['name' => 'Contract', 'documents' => [['name' => 'One to Four Family Residential Contract'], ['name' => 'Third Party Financing Addendum']]]],
            'tasks' => [['id' => 't1', 'name' => 'Earnest money delivered', 'dueDate' => $day(1), 'completed' => true], ['id' => 't2', 'name' => 'HOA documents requested', 'dueDate' => $day(6), 'completed' => false]],
        ]);
        self::write($connection, '90211', [
            'profileId' => '4712', 'name' => '55 Ranch Rd', 'transactionType' => 'LISTING_FOR_SALE', 'status' => 'ACTIVE_LISTING', 'loopUrl' => 'https://www.dotloop.com/my/loop/90211',
            'details' => ['Property Address' => ['Street Number' => '55', 'Street Name' => 'Ranch Rd', 'City' => 'Dripping Springs'], 'Listing Information' => ['Current Price' => '899,000', 'Expiration Date' => $day(90)]],
            'participants' => [['fullName' => 'Tom Becker', 'role' => 'SELLER']], 'folders' => [], 'tasks' => [],
        ]);
    }

    /** @return list<array<array-key, mixed>> */
    private static function list(mixed $value): array
    {
        $rows = [];
        foreach (is_array($value) ? $value : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $data */
    private static function write(Connection $connection, string $id, array $data): SimulatorRecord
    {
        $row = SimulatorRecord::query()->firstOrNew(['connection_id' => $connection->id, 'stream' => 'loops', 'container' => self::CONTAINER, 'external_id' => $id]);
        $row->forceFill(['data' => [...($row->data ?? []), ...$data], 'etag' => substr(sha1((string) microtime(true)), 0, 12), 'deleted' => false])->save();
        $row->forceFill(['updated_at' => CarbonImmutable::now()->addSecond()])->saveQuietly();

        return $row;
    }

    private function tokens(): TokenSet
    {
        return new TokenSet(SimulatorSignature::seal(['account' => 'dl', 'kind' => 'access', 'nonce' => Str::random(8)]), SimulatorSignature::seal(['account' => 'dl', 'kind' => 'refresh']), CarbonImmutable::now()->addHours(12), ['account:read', 'profile:*', 'loop:*']);
    }
}
