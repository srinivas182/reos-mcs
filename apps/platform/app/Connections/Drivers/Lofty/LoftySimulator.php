<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Lofty;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectsWithApiKey;
use App\Connections\Crm\CreatesPeople;
use App\Connections\Crm\CreatesTasks;
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
 * Lofty on demo data (B8.1): two leads, a note and a task per connection,
 * read like the real tool and written back with Lofty's own field names.
 * Any key of four or more characters connects.
 */
final class LoftySimulator implements ConnectorDriver, ConnectsWithApiKey, CreatesPeople, CreatesTasks, CrmDriver
{
    private const CONTAINER = 'lofty';

    public function integration(): string
    {
        return LoftyDriver::INTEGRATION;
    }

    public function accountForKey(string $apiKey): ConnectedAccount
    {
        if (mb_strlen(trim($apiKey)) < 4) {
            throw new AccessRevoked('The demo key is too short.');
        }

        return new ConnectedAccount('lofty-demo-'.substr(sha1($apiKey), 0, 10), 'Lofty (demo)');
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
            $time = $row->updated_at?->utc()->format('Y-m-d H:i:s');
            $records[] = match ($stream) {
                'people' => LoftyRecords::person([...$row->data, 'leadId' => $row->external_id, 'lastUpdateTime' => $time]),
                'notes' => LoftyRecords::note([...$row->data, 'noteId' => $row->external_id, 'lastUpdate' => $time]),
                default => LoftyRecords::task([...$row->data, 'id' => $row->external_id, 'lastUpdate' => $time]),
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

        return self::write($connection, 'notes', null, ['leadId' => $personId, 'content' => $body])->external_id;
    }

    public function createPerson(Connection $connection, string $token, array $person): string
    {
        $this->authorise($connection);

        return self::write($connection, 'people', null, [
            'firstName' => $person['first_name'], 'lastName' => $person['last_name'], 'emails' => $person['emails'], 'phones' => $person['phones'], 'stage' => $person['stage'], 'tags' => [],
        ])->external_id;
    }

    public function createTask(Connection $connection, string $token, string $personId, string $title, ?CarbonImmutable $due): string
    {
        $this->authorise($connection);

        return self::write($connection, 'tasks', null, ['leadId' => $personId, 'content' => $title, 'deadline' => ($due ?? CarbonImmutable::now())->endOfDay()->utc()->getTimestampMs(), 'finishFlag' => false])->external_id;
    }

    public function setTaskDone(Connection $connection, string $token, string $taskId, bool $done): void
    {
        $this->authorise($connection);
        self::write($connection, 'tasks', $taskId, ['finishFlag' => $done]);
    }

    public function updatePerson(Connection $connection, string $token, string $personId, array $fields): void
    {
        $this->authorise($connection);
        $change = array_filter(['stage' => $fields['stage'] ?? null, 'tags' => isset($fields['tags']) ? array_map(static fn (string $t): array => ['tagName' => $t], $fields['tags']) : null], static fn (mixed $v): bool => $v !== null);

        if ($change !== []) {
            self::write($connection, 'people', $personId, $change);
        }
    }

    /**
     * Something changes in "Lofty" itself (demos and tests).
     *
     * @param  'people'|'notes'|'tasks'  $stream
     * @param  array<string, mixed>  $data  Lofty's own field names
     */
    public static function changeInTool(Connection $connection, string $stream, ?string $id, array $data): SimulatorRecord
    {
        self::seed($connection);

        return self::write($connection, $stream, $id, $data);
    }

    /** @return list<array<string, mixed>> what "Lofty" holds now */
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

        self::write($connection, 'people', '3001', ['firstName' => 'Maria', 'lastName' => 'Lopez', 'emails' => ['maria.lopez@example.com'], 'phones' => ['832-555-0123'], 'stage' => 'Nurture',
            'tags' => [['tagName' => 'katy-buyer']], 'source' => 'Website']);
        self::write($connection, 'people', '3002', ['firstName' => 'Priya', 'lastName' => 'Shah', 'emails' => ['priya.shah@example.com'], 'phones' => ['281-555-0144'], 'stage' => 'New', 'tags' => []]);
        self::write($connection, 'notes', '7001', ['leadId' => '3002', 'content' => 'Pre-approved to $450k; wants Sugar Land schools.']);
        self::write($connection, 'tasks', '9001', ['leadId' => '3002', 'content' => 'Send Sugar Land listings to Priya', 'deadline' => CarbonImmutable::now()->addDay()->utc()->format('Y-m-d H:i:s'), 'finishFlag' => false]);
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
        throw new LogicException('Lofty connects with an API key.');
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        throw new LogicException('Lofty connects with an API key.');
    }

    public function refresh(string $refreshToken): TokenSet
    {
        throw new AccessRevoked('API keys have no refresh.');
    }

    public function revoke(string $token): void {}

    public function account(TokenSet $tokens): ConnectedAccount
    {
        return $this->accountForKey($tokens->accessToken);
    }
}
