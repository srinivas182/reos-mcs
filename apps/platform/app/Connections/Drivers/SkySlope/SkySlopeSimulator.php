<?php

declare(strict_types=1);

namespace App\Connections\Drivers\SkySlope;

use App\Connections\AcceptsDocuments;
use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectsWithApiKey;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Stores\DealStore;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use App\Models\Deal;
use App\Models\SimulatorRecord;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use LogicException;

/**
 * A brokerage's SkySlope on demo data (B9.2): a sale and a listing for two
 * of its agents and a sale for someone with no REOS account, in SkySlope's
 * field names. Any "key:secret" of four or more characters each connects.
 */
final class SkySlopeSimulator implements AcceptsDocuments, ConnectorDriver, ConnectsWithApiKey
{
    private const CONTAINER = 'ss';

    public const OWNERS = ['james.miller@demo.reos.test', 'maria.garcia@demo.reos.test', 'tc@lonestar.example'];

    public function integration(): string
    {
        return SkySlopeDriver::INTEGRATION;
    }

    public function accountForKey(string $apiKey): ConnectedAccount
    {
        [$key, $secret] = SkySlopeDriver::split($apiKey);

        if (mb_strlen($key) < 4 || mb_strlen($secret) < 4) {
            throw new AccessRevoked('The demo key is too short.');
        }

        return new ConnectedAccount('ss-demo-'.substr(sha1($key), 0, 10), 'SkySlope (demo)');
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        throw new LogicException('SkySlope connects with the brokerage\'s access key.');
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        throw new LogicException('SkySlope connects with the brokerage\'s access key.');
    }

    public function refresh(string $refreshToken): TokenSet
    {
        throw new AccessRevoked('Access keys have no refresh.');
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

        foreach (SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->orderBy('id')->get() as $row) {
            if ($row->deleted) {
                $removed[] = $row->stream.':'.$row->external_id;

                continue;
            }
            $records[] = SkySlopeRecords::deal([...$row->data, ($row->stream === 'sale' ? 'saleGuid' : 'listingGuid') => $row->external_id, 'modifiedOn' => $row->updated_at?->toIso8601ZuluString()], $row->stream);
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

    /** The signed copy onto the demo sale or listing; it shows on the deal at the next sync. */
    public function uploadDocument(Connection $connection, string $accessToken, Deal $deal, string $name, string $contents): bool
    {
        [$kind, $guid] = array_pad(explode(':', (string) $deal->external_id, 2), 2, '');
        $row = SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->where('stream', $kind)->where('external_id', $guid)->first();

        if ($row === null || $contents === '') {
            return false;
        }

        $documents = is_array($row->data['documents'] ?? null) ? $row->data['documents'] : [];
        self::write($connection, $kind, $guid, ['documents' => [...$documents, ['fileName' => $name]]]);

        return true;
    }

    /** @param  array<string, mixed>  $data  SkySlope's own field names */
    public static function changeInTool(Connection $connection, string $kind, string $id, array $data, bool $deleted = false): SimulatorRecord
    {
        self::seed($connection);
        $row = self::write($connection, $kind, $id, $data);
        $row->forceFill(['deleted' => $deleted])->saveQuietly();

        return $row;
    }

    public static function seed(Connection $connection): void
    {
        if (SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->exists()) {
            return;
        }

        [$james, $maria, $tc] = self::OWNERS;
        $day = static fn (int $days): string => CarbonImmutable::now('America/Chicago')->addDays($days)->toDateString().'T00:00:00';
        self::write($connection, 'sale', 'ss-sale-1', ['email' => $james, 'status' => 'pending', 'salePrice' => 389000, 'escrowClosingDate' => $day(21), 'contractAcceptedDate' => $day(-9),
            'property' => ['streetNumber' => '7', 'streetAddress' => 'Cypress Creek Pkwy', 'city' => 'Houston'], 'buyers' => [['firstName' => 'Lena', 'lastName' => 'Ortiz', 'email' => 'lena@example.com']]]);
        self::write($connection, 'listing', 'ss-listing-1', ['email' => $maria, 'status' => 'active', 'listingPrice' => 725000, 'expirationDate' => $day(120),
            'property' => ['streetNumber' => '310', 'streetAddress' => 'Bluebonnet Ln', 'city' => 'Katy'], 'sellers' => [['firstName' => 'Omar', 'lastName' => 'Haddad']]]);
        self::write($connection, 'sale', 'ss-sale-2', ['email' => $tc, 'status' => 'active', 'salePrice' => 250000, 'property' => ['streetNumber' => '1', 'streetAddress' => 'Main St', 'city' => 'Houston']]);
    }

    /** @param  array<string, mixed>  $data */
    private static function write(Connection $connection, string $kind, string $id, array $data): SimulatorRecord
    {
        $row = SimulatorRecord::query()->firstOrNew(['connection_id' => $connection->id, 'stream' => $kind, 'container' => self::CONTAINER, 'external_id' => $id]);
        $row->forceFill(['data' => [...($row->data ?? []), ...$data], 'etag' => substr(sha1((string) microtime(true)), 0, 12), 'deleted' => false])->save();
        $row->forceFill(['updated_at' => CarbonImmutable::now()->addSecond()])->saveQuietly();

        return $row;
    }
}
