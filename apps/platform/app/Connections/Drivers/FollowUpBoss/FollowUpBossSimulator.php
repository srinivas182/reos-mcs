<?php

declare(strict_types=1);

namespace App\Connections\Drivers\FollowUpBoss;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectsWithApiKey;
use App\Connections\Crm\CrmDriver;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\SimulatorSignature;
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
 * Follow Up Boss on demo data (B7.2): the same contract as the real driver,
 * with a Houston agent's people, notes and a task kept per connection.
 * Any key of four or more characters connects. `changeInTool()` lets demos
 * and tests write in "Follow Up Boss" and see it arrive in REOS.
 */
final class FollowUpBossSimulator implements ConnectorDriver, ConnectsWithApiKey, CrmDriver
{
    private const CONTAINER = 'fub';

    private const PAGE = 50;

    public function integration(): string
    {
        return FollowUpBossDriver::INTEGRATION;
    }

    public function accountForKey(string $apiKey): ConnectedAccount
    {
        if (mb_strlen(trim($apiKey)) < 4) {
            throw new AccessRevoked('The demo key is too short.');
        }

        return new ConnectedAccount('fub-demo-'.substr(sha1($apiKey), 0, 10), 'Follow Up Boss (demo)');
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
        $offset = is_int($state['offset'] ?? null) ? $state['offset'] : 0;
        $rows = SimulatorRecord::query()->where('connection_id', $connection->id)->where('stream', $stream)->where('container', self::CONTAINER)
            ->when($after !== null, static fn ($q) => $q->where('updated_at', '>=', $after))
            ->orderBy('updated_at')->orderBy('id')->skip($offset)->take(self::PAGE + 1)->get();
        $records = [];
        $removed = [];

        foreach ($rows->take(self::PAGE) as $row) {
            $data = [...$row->data, 'id' => $row->external_id, 'updated' => $row->updated_at?->toIso8601String()];

            if ($row->deleted) {
                $removed[] = $row->external_id;

                continue;
            }

            $records[] = match ($stream) {
                'people' => FollowUpBossRecords::person($data),
                'notes' => FollowUpBossRecords::note($data),
                default => FollowUpBossRecords::task($data),
            };
        }

        if ($rows->count() > self::PAGE) {
            return new SyncPage($records, $removed, (string) json_encode(['since' => $state['since'] ?? null, 'offset' => $offset + self::PAGE]), false);
        }

        return new SyncPage($records, $removed, (string) json_encode(['since' => CarbonImmutable::now()->subSecond()->toIso8601String()]), true);
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

        return is_int($connection) && is_string($delivery) ? new WebhookDelivery($delivery, [$connection]) : null;
    }

    public function createNote(Connection $connection, string $token, string $personId, string $body): string
    {
        $this->authorise($connection);

        return self::write($connection, 'notes', null, ['personId' => $personId, 'subject' => 'REOS', 'body' => $body])->external_id;
    }

    public function setTaskDone(Connection $connection, string $token, string $taskId, bool $done): void
    {
        $this->authorise($connection);
        self::write($connection, 'tasks', $taskId, ['isCompleted' => $done]);
    }

    public function updatePerson(Connection $connection, string $token, string $personId, array $fields): void
    {
        $this->authorise($connection);
        self::write($connection, 'people', $personId, array_filter(['stage' => $fields['stage'] ?? null, 'tags' => $fields['tags'] ?? null], static fn (mixed $v): bool => $v !== null));
    }

    /**
     * Something changes in "Follow Up Boss" itself (demos and tests).
     *
     * @param  'people'|'notes'|'tasks'  $stream
     * @param  array<string, mixed>  $data  Follow Up Boss's own field names
     */
    public static function changeInTool(Connection $connection, string $stream, ?string $id, array $data, bool $deleted = false): SimulatorRecord
    {
        self::seed($connection);
        $row = self::write($connection, $stream, $id, $data);

        if ($deleted) {
            $row->forceFill(['deleted' => true])->save();
        }

        return $row;
    }

    /** @return list<array<string, mixed>> what "Follow Up Boss" holds now */
    public static function records(Connection $connection, string $stream): array
    {
        return array_values(SimulatorRecord::query()->where('connection_id', $connection->id)->where('stream', $stream)->where('deleted', false)->orderBy('id')->get()
            ->map(static fn (SimulatorRecord $r): array => [...$r->data, 'id' => $r->external_id])->all());
    }

    /** A Houston agent's Follow Up Boss, the first time a connection reads it. */
    public static function seed(Connection $connection): void
    {
        if (SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->exists()) {
            return;
        }

        $people = [
            ['1001', 'Maria', 'Lopez', 'maria.lopez@example.com', '(832) 555-0123', 'Lead', ['Buyer', 'Katy']],
            ['1002', 'David', 'Chen', 'dchen@example.com', null, 'Active client', ['Seller']],
            ['1003', 'Priya', 'Shah', 'priya.shah@example.com', '713-555-0188', 'Nurture', []],
            ['1004', 'Mike', 'Johnson', null, '281-555-0101', 'Lead', []],
        ];

        foreach ($people as [$id, $first, $last, $email, $phone, $stage, $tags]) {
            self::write($connection, 'people', $id, [
                'firstName' => $first, 'lastName' => $last, 'stage' => $stage, 'tags' => $tags,
                'emails' => $email === null ? [] : [['value' => $email, 'type' => 'home']],
                'phones' => $phone === null ? [] : [['value' => $phone, 'type' => 'mobile']],
            ]);
        }

        self::write($connection, 'notes', '5001', ['personId' => '1001', 'subject' => 'Call', 'body' => 'Wants 3 bedrooms in Katy ISD, up to $420k.']);
        self::write($connection, 'tasks', '7001', ['personId' => '1002', 'name' => 'Send David the listing agreement', 'dueDate' => CarbonImmutable::now()->addDays(2)->toDateString(), 'isCompleted' => false]);
    }

    /** @param  array<string, mixed>  $data */
    private static function write(Connection $connection, string $stream, ?string $id, array $data): SimulatorRecord
    {
        $id ??= (string) random_int(10000, 99999999);
        $row = SimulatorRecord::query()->firstOrNew(['connection_id' => $connection->id, 'stream' => $stream, 'container' => self::CONTAINER, 'external_id' => $id]);
        $row->forceFill(['data' => [...($row->data ?? []), ...$data], 'etag' => substr(sha1((string) microtime(true)), 0, 12), 'deleted' => false])->save();
        // Each change is newer than the last sync's mark.
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
        throw new LogicException('Follow Up Boss connects with an API key.');
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        throw new LogicException('Follow Up Boss connects with an API key.');
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
