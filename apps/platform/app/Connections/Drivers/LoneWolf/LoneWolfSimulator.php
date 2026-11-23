<?php

declare(strict_types=1);

namespace App\Connections\Drivers\LoneWolf;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectsWithApiKey;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Stores\DealStore;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use App\Models\SimulatorRecord;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use LogicException;

/**
 * A brokerage's Lone Wolf Transact on demo data (B9.1b): transactions for
 * two of the brokerage's agents and one for someone with no REOS account,
 * with contacts, documents, an accepted offer and Texas key dates, in
 * Transact's own field names. Any client ID of eight or more characters
 * connects. Everything is read in one page, with removals.
 */
final class LoneWolfSimulator implements ConnectorDriver, ConnectsWithApiKey
{
    private const CONTAINER = 'lw';

    /** Who the demo transactions belong to: two agents, and a user with no REOS account. */
    public const OWNERS = ['james.miller@demo.reos.test', 'maria.garcia@demo.reos.test', 'coordinator@lonestar.example'];

    public function integration(): string
    {
        return LoneWolfDriver::INTEGRATION;
    }

    public function accountForKey(string $apiKey): ConnectedAccount
    {
        if (mb_strlen(trim($apiKey)) < 8) {
            throw new AccessRevoked('The demo client ID is too short.');
        }

        return new ConnectedAccount('lw-demo-'.substr(sha1(trim($apiKey)), 0, 10), 'Lone Star Realty (demo)');
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        throw new LogicException('Lone Wolf connects with the brokerage\'s client ID.');
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        throw new LogicException('Lone Wolf connects with the brokerage\'s client ID.');
    }

    public function refresh(string $refreshToken): TokenSet
    {
        throw new AccessRevoked('Client IDs have no refresh.');
    }

    public function revoke(string $token): void {}

    public function account(TokenSet $tokens): ConnectedAccount
    {
        return $this->accountForKey($tokens->accessToken);
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

        $records = [];
        $removed = [];
        foreach (SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->where('stream', 'transactions')->orderBy('id')->get() as $row) {
            if ($row->deleted) {
                $removed[] = $row->external_id;

                continue;
            }
            $data = $row->data;
            $records[] = LoneWolfRecords::deal(
                [...$data, 'id' => $row->external_id, 'modifiedDate' => $row->updated_at?->toIso8601ZuluString()],
                self::list($data['contacts'] ?? null),
                self::list($data['documents'] ?? null),
                self::list($data['offers'] ?? null),
                is_string($data['userEmail'] ?? null) ? $data['userEmail'] : null,
            );
        }

        return new SyncPage($records, $removed, null, true);
    }

    public function requestsPerMinute(): int
    {
        return 600;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        return null;
    }

    /**
     * Something changes in "Transact" itself (demos and tests): fields merge
     * into the transaction; `deleted` removes it.
     *
     * @param  array<string, mixed>  $data  Transact's own field names
     */
    public static function changeInTool(Connection $connection, string $id, array $data, bool $deleted = false): SimulatorRecord
    {
        self::seed($connection);
        $row = self::write($connection, $id, $data);
        $row->forceFill(['deleted' => $deleted])->saveQuietly();

        return $row;
    }

    public static function seed(Connection $connection): void
    {
        if (SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->exists()) {
            return;
        }

        [$james, $maria, $coordinator] = self::OWNERS;
        $day = static fn (int $days): string => CarbonImmutable::now('America/Chicago')->addDays($days)->toDateString();

        self::write($connection, 'lw-tx-1001', [
            'userEmail' => $james, 'type' => 1, 'phase' => 2, 'address1' => '2210 Heights Blvd', 'locality' => 'Houston', 'region' => 'TX', 'asking' => 515000,
            'contacts' => [['contactType' => 0, 'firstname' => 'Daniel', 'lastname' => 'Kim', 'email' => 'daniel.kim@example.com', 'cellPhone' => '713-555-0170'], ['contactType' => 4, 'companyName' => 'Bayou Mortgage']],
            'documents' => [['name' => 'One to Four Family Residential Contract', 'status' => 'complete'], ['name' => 'Third Party Financing Addendum', 'status' => 'complete'], ['name' => 'Seller\'s Disclosure Notice', 'status' => 'missing']],
            'offers' => [['purchasePrice' => 505000, 'commission' => 15150, 'acceptanceDate' => $day(-4), 'closingDate' => $day(26), 'finalWalkthroughDate' => $day(25)]],
            'keyDates' => [['id' => 'opt', 'name' => 'Option period ends', 'date' => $day(3)], ['id' => 'fin', 'name' => 'Financing approval', 'date' => $day(17)], ['id' => 'svy', 'name' => 'Survey due', 'date' => $day(10)]],
        ]);
        self::write($connection, 'lw-tx-1002', [
            'userEmail' => $maria, 'type' => 0, 'phase' => 1, 'address1' => '418 Magnolia Ct', 'locality' => 'Sugar Land', 'region' => 'TX', 'asking' => 689000, 'listingExpiration' => $day(74),
            'contacts' => [['contactType' => 1, 'firstname' => 'Grace', 'lastname' => 'Okafor', 'email' => 'grace.okafor@example.com']],
            'documents' => [['name' => 'Residential Listing Agreement', 'status' => 'complete'], ['name' => 'Information About Brokerage Services', 'status' => 'complete']],
            'offers' => [],
        ]);
        self::write($connection, 'lw-tx-1003', [
            'userEmail' => $coordinator, 'type' => 1, 'phase' => 2, 'address1' => '90 Office Park Dr', 'locality' => 'Houston', 'asking' => 300000, 'contacts' => [], 'documents' => [], 'offers' => [],
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
        $row = SimulatorRecord::query()->firstOrNew(['connection_id' => $connection->id, 'stream' => 'transactions', 'container' => self::CONTAINER, 'external_id' => $id]);
        $row->forceFill(['data' => [...($row->data ?? []), ...$data], 'etag' => substr(sha1((string) microtime(true)), 0, 12), 'deleted' => false])->save();
        $row->forceFill(['updated_at' => CarbonImmutable::now()->addSecond()])->saveQuietly();

        return $row;
    }
}
