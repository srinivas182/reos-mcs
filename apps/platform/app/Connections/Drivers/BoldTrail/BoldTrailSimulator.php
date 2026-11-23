<?php

declare(strict_types=1);

namespace App\Connections\Drivers\BoldTrail;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectsWithApiKey;
use App\Connections\Crm\CrmDriver;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Stores\ContactNoteStore;
use App\Connections\Stores\ContactStore;
use App\Connections\Stores\ContactTaskStore;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use App\Models\SimulatorRecord;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use LogicException;

/**
 * kvCORE / BoldTrail on demo data (B7.3): contacts (one on a smart
 * campaign), a note and a task per connection, read by polling like the real
 * tool. Any token of four or more characters connects.
 */
final class BoldTrailSimulator implements ConnectorDriver, ConnectsWithApiKey, CrmDriver
{
    private const CONTAINER = 'kv';

    public function integration(): string
    {
        return BoldTrailDriver::INTEGRATION;
    }

    public function accountForKey(string $apiKey): ConnectedAccount
    {
        if (mb_strlen(trim($apiKey)) < 4) {
            throw new AccessRevoked('The demo token is too short.');
        }

        return new ConnectedAccount('kv-demo-'.substr(sha1($apiKey), 0, 10), 'kvCORE (demo)');
    }

    public function streams(): array
    {
        return ['people' => ContactStore::class, 'notes' => ContactNoteStore::class, 'tasks' => ContactTaskStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $this->authorise($connection);
        $state = is_string($cursor) ? (array) json_decode($cursor, true) : [];
        $after = is_string($state['since'] ?? null) ? CarbonImmutable::parse($state['since']) : null;
        $rows = SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->where('stream', $stream)
            ->when($after !== null, static fn ($q) => $q->where('updated_at', '>=', $after))->orderBy('id')->get();
        $records = [];

        foreach ($rows as $row) {
            $data = [...$row->data, 'id' => $row->external_id, 'date_modified' => $row->updated_at?->toIso8601String(), 'updated_at' => $row->updated_at?->toIso8601String()];
            $records[] = match ($stream) {
                'people' => BoldTrailRecords::person($data),
                'notes' => BoldTrailRecords::note($data, is_scalar($data['contact_id'] ?? null) ? (string) $data['contact_id'] : ''),
                default => BoldTrailRecords::task($data),
            };
        }

        return new SyncPage($records, [], (string) json_encode(['since' => CarbonImmutable::now()->subSecond()->toIso8601String()]), true);
    }

    public function requestsPerMinute(): int
    {
        return 600;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        return null;
    }

    public function createNote(Connection $connection, string $token, string $personId, string $body): string
    {
        $this->authorise($connection);

        return self::write($connection, 'notes', null, ['contact_id' => $personId, 'details' => $body])->external_id;
    }

    public function setTaskDone(Connection $connection, string $token, string $taskId, bool $done): void
    {
        $this->authorise($connection);
        self::write($connection, 'tasks', $taskId, ['completed' => $done ? 1 : 0]);
    }

    public function updatePerson(Connection $connection, string $token, string $personId, array $fields): void
    {
        $this->authorise($connection);
        $status = isset($fields['stage']) ? BoldTrailRecords::status($fields['stage']) : null;

        if ($status !== null) {
            self::write($connection, 'people', $personId, ['status' => $status]);
        }
    }

    /**
     * Something changes in "kvCORE" itself (demos and tests).
     *
     * @param  'people'|'notes'|'tasks'  $stream
     * @param  array<string, mixed>  $data  kvCORE's own field names
     */
    public static function changeInTool(Connection $connection, string $stream, ?string $id, array $data): SimulatorRecord
    {
        self::seed($connection);

        return self::write($connection, $stream, $id, $data);
    }

    /** @return list<array<string, mixed>> what "kvCORE" holds now */
    public static function records(Connection $connection, string $stream): array
    {
        return array_values(SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->where('stream', $stream)->orderBy('id')->get()
            ->map(static fn (SimulatorRecord $r): array => [...$r->data, 'id' => $r->external_id])->all());
    }

    public static function seed(Connection $connection): void
    {
        if (SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->exists()) {
            return;
        }

        self::write($connection, 'people', '2001', ['first_name' => 'Maria', 'last_name' => 'Lopez', 'email' => 'maria.lopez@example.com', 'cell_phone_1' => '832-555-0123', 'status' => 4, 'hashtags' => ['katy-buyer'],
            'smart_campaign' => ['name' => 'Buyer nurture: 90 days', 'status' => 'Active']]);
        self::write($connection, 'people', '2002', ['first_name' => 'Tom', 'last_name' => 'Nguyen', 'email' => 'tom.nguyen@example.com', 'cell_phone_1' => '713-555-0199', 'status' => 7, 'hashtags' => []]);
        self::write($connection, 'notes', '6001', ['contact_id' => '2002', 'details' => 'Relocating from Dallas in March; needs a 30-day close.']);
        self::write($connection, 'tasks', '8001', ['contact_id' => '2002', 'title' => 'Call Tom about Bellaire listings', 'due_date' => CarbonImmutable::now()->addDay()->toDateString(), 'completed' => 0]);
    }

    /** @param  array<string, mixed>  $data */
    private static function write(Connection $connection, string $stream, ?string $id, array $data): SimulatorRecord
    {
        $id ??= (string) random_int(10000, 99999999);
        $row = SimulatorRecord::query()->firstOrNew(['connection_id' => $connection->id, 'stream' => $stream, 'container' => self::CONTAINER, 'external_id' => $id]);
        $row->forceFill(['data' => [...($row->data ?? []), ...$data], 'etag' => substr(sha1((string) microtime(true)), 0, 12), 'deleted' => false])->save();
        $row->forceFill(['updated_at' => CarbonImmutable::now()->addSecond()])->saveQuietly();

        return $row;
    }

    private function authorise(Connection $connection): void
    {
        if (($connection->meta['simulator_revoked'] ?? false) === true) {
            throw new AccessRevoked('Simulator revoked the connection.');
        }

        self::seed($connection);
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        throw new LogicException('kvCORE connects with an API token.');
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        throw new LogicException('kvCORE connects with an API token.');
    }

    public function refresh(string $refreshToken): TokenSet
    {
        throw new AccessRevoked('API tokens have no refresh.');
    }

    public function revoke(string $token): void {}

    public function account(TokenSet $tokens): ConnectedAccount
    {
        return $this->accountForKey($tokens->accessToken);
    }
}
