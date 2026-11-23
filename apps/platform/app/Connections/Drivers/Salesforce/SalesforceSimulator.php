<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Salesforce;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Crm\CreatesTasks;
use App\Connections\Crm\CrmDriver;
use App\Connections\Crm\MapsStageFields;
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
use Illuminate\Support\Str;

/**
 * A brokerage's Salesforce org on demo data (B8.2): the demo sign-in page,
 * Contacts and Leads owned by two agents and by someone with no REOS
 * account, a note and a task, read and written with Salesforce's own field
 * names and the brokerage's stage mapping.
 */
final class SalesforceSimulator implements ConnectorDriver, CreatesTasks, CrmDriver, MapsStageFields
{
    private const CONTAINER = 'sf';

    /** Who owns the demo records: two of the brokerage's agents, and an assistant with no REOS account. */
    public const OWNERS = ['james.miller@demo.reos.test', 'maria.garcia@demo.reos.test', 'assistant@lonestar.example'];

    public function integration(): string
    {
        return SalesforceDriver::INTEGRATION;
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
        return new ConnectedAccount('00Dsim0000000001', 'admin@lonestar-realty.example (demo)');
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
        $map = SalesforceRecords::mapping($connection->meta);
        $rows = SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->where('stream', $stream)
            ->when($after !== null, static fn ($q) => $q->where('updated_at', '>=', $after))->orderBy('id')->get();
        $records = [];
        $removed = [];

        foreach ($rows as $row) {
            $data = [...$row->data, 'Id' => $row->external_id, 'SystemModstamp' => $row->updated_at?->toIso8601ZuluString(), 'LastModifiedDate' => $row->updated_at?->toIso8601ZuluString()];
            if ($row->deleted) {
                $removed[] = $row->external_id;

                continue;
            }
            $lead = str_starts_with($row->external_id, SalesforceRecords::LEAD);
            if ($stream === 'people' && $lead && ! $map['leads']) {
                continue;
            }
            $records[] = match ($stream) {
                'people' => SalesforceRecords::person($data, $lead ? $map['lead_stage'] : $map['contact_stage']),
                'notes' => SalesforceRecords::note($data, is_string($data['LinkedEntityId'] ?? null) ? $data['LinkedEntityId'] : ''),
                default => SalesforceRecords::task($data),
            };
        }

        return new SyncPage($records, $removed, (string) json_encode(['since' => CarbonImmutable::now()->subSecond()->toIso8601String()]), true);
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

        return self::write($connection, 'notes', '069'.Str::lower(Str::random(12)), ['Title' => mb_substr($body, 0, 80), 'TextPreview' => mb_substr($body, 0, 255), 'LinkedEntityId' => $personId])->external_id;
    }

    public function createTask(Connection $connection, string $token, string $personId, string $title, ?CarbonImmutable $due): string
    {
        $this->authorise($connection);
        $person = SimulatorRecord::query()->where('connection_id', $connection->id)->where('stream', 'people')->where('external_id', $personId)->first();
        $owner = $person === null ? [] : ($person->data['Owner'] ?? []);

        return self::write($connection, 'tasks', '00Tsim'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT), [
            'WhoId' => $personId, 'Subject' => $title, 'Status' => 'Not Started', 'IsClosed' => false, 'ActivityDate' => $due?->toDateString(), 'Owner' => $owner,
        ])->external_id;
    }

    public function setTaskDone(Connection $connection, string $token, string $taskId, bool $done): void
    {
        $this->authorise($connection);
        self::write($connection, 'tasks', $taskId, ['Status' => $done ? 'Completed' : 'Not Started', 'IsClosed' => $done]);
    }

    public function updatePerson(Connection $connection, string $token, string $personId, array $fields): void
    {
        $this->authorise($connection);
        $map = SalesforceRecords::mapping($connection->meta);
        $field = str_starts_with($personId, SalesforceRecords::LEAD) ? $map['lead_stage'] : $map['contact_stage'];

        if ($field !== null && isset($fields['stage'])) {
            self::write($connection, 'people', $personId, [$field => $fields['stage']]);
        }
    }

    /** @return array{Contact: list<array{name: string, label: string}>, Lead: list<array{name: string, label: string}>} */
    public function stageFields(string $token): array
    {
        return [
            'Contact' => [['name' => 'Stage__c', 'label' => 'Stage'], ['name' => 'LeadSource', 'label' => 'Lead Source']],
            'Lead' => [['name' => 'Status', 'label' => 'Lead Status'], ['name' => 'Rating', 'label' => 'Rating']],
        ];
    }

    /**
     * Something changes in "Salesforce" itself (demos and tests).
     *
     * @param  'people'|'notes'|'tasks'  $stream
     * @param  array<string, mixed>  $data  Salesforce's own field names
     */
    public static function changeInTool(Connection $connection, string $stream, ?string $id, array $data, bool $deleted = false): SimulatorRecord
    {
        self::seed($connection);
        $row = self::write($connection, $stream, $id, $data);
        $row->forceFill(['deleted' => $deleted])->saveQuietly();

        return $row;
    }

    /** @return list<array<string, mixed>> what "Salesforce" holds now */
    public static function records(Connection $connection, string $stream): array
    {
        return array_values(SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->where('stream', $stream)->orderBy('id')->get()
            ->map(static fn (SimulatorRecord $r): array => [...$r->data, 'Id' => $r->external_id])->all());
    }

    public static function seed(Connection $connection): void
    {
        if (SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->exists()) {
            return;
        }

        [$james, $maria, $assistant] = self::OWNERS;
        self::write($connection, 'people', '003sim000000001', ['FirstName' => 'Daniel', 'LastName' => 'Kim', 'Email' => 'daniel.kim@example.com', 'MobilePhone' => '713-555-0170', 'Stage__c' => 'Active buyer', 'Owner' => ['Email' => $james]]);
        self::write($connection, 'people', '003sim000000002', ['FirstName' => 'Grace', 'LastName' => 'Okafor', 'Email' => 'grace.okafor@example.com', 'Phone' => '281-555-0155', 'Stage__c' => 'Past client', 'Owner' => ['Email' => $maria]]);
        self::write($connection, 'people', '00Qsim000000001', ['FirstName' => 'Leo', 'LastName' => 'Brandt', 'Email' => 'leo.brandt@example.com', 'Status' => 'Open - Not Contacted', 'Owner' => ['Email' => $james]]);
        self::write($connection, 'people', '003sim000000003', ['FirstName' => 'Office', 'LastName' => 'Walk-in', 'Email' => 'walkin@example.com', 'Owner' => ['Email' => $assistant]]);
        self::write($connection, 'notes', '069sim000000001', ['Title' => 'Showing feedback', 'TextPreview' => 'Liked the Heights bungalow; worried about the commute.', 'LinkedEntityId' => '003sim000000001']);
        self::write($connection, 'tasks', '00Tsim000000001', ['Subject' => 'Send Daniel the Heights comps', 'ActivityDate' => CarbonImmutable::now()->addDay()->toDateString(), 'Status' => 'Not Started', 'IsClosed' => false,
            'WhoId' => '003sim000000001', 'Owner' => ['Email' => $james]]);
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

    private function tokens(): TokenSet
    {
        return new TokenSet(
            'https://lonestar.my.salesforce.example '.SimulatorSignature::seal(['account' => 'sf', 'kind' => 'access', 'nonce' => Str::random(8)]),
            SimulatorSignature::seal(['account' => 'sf', 'kind' => 'refresh']),
            CarbonImmutable::now()->addHour(),
            ['api', 'refresh_token'],
        );
    }
}
