<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Salesforce;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Crm\CreatesTasks;
use App\Connections\Crm\CrmDriver;
use App\Connections\Crm\MapsStageFields;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\Stores\ContactNoteStore;
use App\Connections\Stores\ContactStore;
use App\Connections\Stores\ContactTaskStore;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Salesforce (B8.2, ADR 0033, decision 3: standard objects). The brokerage
 * connects its org once with OAuth 2 (web server flow with PKCE) from
 * Brokerage integrations (screen 40); the connection is the brokerage's.
 *
 * - Read: Contacts and Leads (not converted) as people, Tasks on a person,
 *   and ContentNotes linked to a person, each changed since the last sync
 *   (`SystemModstamp`, or `LastModifiedDate` for notes), every 15 minutes.
 *   Change Data Capture needs a paid edition and is later.
 * - Each record goes to the agent whose email is the record owner's
 *   (`RecordOwners`): agents see only what they own; the broker sees counts.
 * - Write: notes (a ContentNote linked to the person), task Status, and the
 *   person's stage in the field the brokerage mapped (screen 40).
 *
 * Salesforce answers each org at its own address (`instance_url`), which
 * REOS keeps with the access token as "<instance_url> <token>".
 */
final readonly class SalesforceDriver implements ConnectorDriver, CreatesTasks, CrmDriver, MapsStageFields
{
    public const INTEGRATION = 'salesforce';

    public const VERSION = 'v62.0';

    public const LOGIN = 'https://login.salesforce.com';

    public const STREAMS = ['people' => 'both', 'notes' => 'both', 'tasks' => 'both'];

    /** @var array{can: list<string>, never: list<string>} */
    public const CONSENT = ['can' => ['read_people', 'write_notes', 'write_tasks', 'write_stage'], 'never' => ['delete_people', 'send_messages']];

    /** Sessions last two hours by default; REOS refreshes a little before. */
    private const SESSION_MINUTES = 100;

    /** Notes whose preview is this long are fetched in full. */
    private const PREVIEW = 250;

    public function __construct(private string $clientId, private string $clientSecret, private string $loginUrl = '') {}

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return $this->login().'/services/oauth2/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => $this->clientId, 'redirect_uri' => $redirectUri, 'state' => $state,
            'code_challenge' => $codeChallenge, 'code_challenge_method' => 'S256', 'scope' => 'api refresh_token', 'prompt' => 'login consent',
        ]);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        return $this->tokens($this->json(Http::asForm()->acceptJson()->timeout(30)->post($this->login().'/services/oauth2/token', [
            'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri, 'code_verifier' => $codeVerifier,
        ])), null);
    }

    public function refresh(string $refreshToken): TokenSet
    {
        return $this->tokens($this->json(Http::asForm()->acceptJson()->timeout(30)->post($this->login().'/services/oauth2/token', [
            'grant_type' => 'refresh_token', 'refresh_token' => $refreshToken, 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
        ])), $refreshToken);
    }

    public function revoke(string $token): void
    {
        [, $access] = self::split($token);
        rescue(fn () => Http::asForm()->timeout(10)->post($this->login().'/services/oauth2/revoke', ['token' => $access]), report: false);
    }

    public function account(TokenSet $tokens): ConnectedAccount
    {
        $me = $this->json($this->http($tokens->accessToken)->get(self::split($tokens->accessToken)[0].'/services/oauth2/userinfo'));
        $org = $me['organization_id'] ?? null;

        if (! is_string($org) || $org === '') {
            throw new AccessRevoked('Salesforce returned no org.');
        }

        return new ConnectedAccount($org, is_string($me['preferred_username'] ?? null) ? $me['preferred_username'] : 'Salesforce');
    }

    public function streams(): array
    {
        return ['people' => ContactStore::class, 'notes' => ContactNoteStore::class, 'tasks' => ContactTaskStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $state = is_string($cursor) ? (array) json_decode($cursor, true) : [];
        $map = SalesforceRecords::mapping($connection->meta);
        $objects = match ($stream) {
            'people' => $map['leads'] ? ['Contact', 'Lead'] : ['Contact'],
            'notes' => ['ContentNote'],
            default => ['Task'],
        };
        $marks = is_array($state['marks'] ?? null) ? $state['marks'] : [];
        $object = is_string($state['object'] ?? null) && in_array($state['object'], $objects, true) ? $state['object'] : $objects[0];
        $mark = is_string($marks[$object] ?? null) ? $marks[$object] : $since?->utc()->format('Y-m-d\TH:i:s\Z');
        $next = is_string($state['next'] ?? null) ? $state['next'] : null;
        [$instance] = self::split($accessToken);

        $body = $next !== null
            ? $this->json($this->http($accessToken)->get($instance.$next))
            : $this->json($this->http($accessToken)->get($instance.'/services/data/'.self::VERSION.'/query', ['q' => self::soql($object, $map, $mark)]));
        $rows = is_array($body['records'] ?? null) ? array_values(array_filter($body['records'], is_array(...))) : [];
        $records = match ($object) {
            'Contact' => array_map(static fn (array $r): array => SalesforceRecords::person($r, $map['contact_stage']), $rows),
            'Lead' => array_map(static fn (array $r): array => SalesforceRecords::person($r, $map['lead_stage']), $rows),
            'ContentNote' => $this->notes($accessToken, $rows),
            default => array_map(SalesforceRecords::task(...), $rows),
        };

        $newest = is_array($state['newest'] ?? null) ? $state['newest'] : [];
        $field = $object === 'ContentNote' ? 'LastModifiedDate' : 'SystemModstamp';
        foreach ($rows as $row) {
            $at = $row[$field] ?? null;
            if (is_string($at) && (! is_string($newest[$object] ?? null) || $at > $newest[$object])) {
                $newest[$object] = $at;
            }
        }

        $nextUrl = ($body['done'] ?? true) === false && is_string($body['nextRecordsUrl'] ?? null) ? $body['nextRecordsUrl'] : null;
        if ($nextUrl !== null) {
            return new SyncPage($records, [], (string) json_encode(['object' => $object, 'next' => $nextUrl, 'marks' => $marks, 'newest' => $newest]), false);
        }

        // People deleted in Salesforce since the mark (the last 15 days are kept there).
        $removed = in_array($object, ['Contact', 'Lead'], true) && $mark !== null ? $this->deleted($accessToken, $object, $mark) : [];

        // This object is done: its mark moves on; then the next object, or finished.
        $marks[$object] = $newest[$object] ?? $mark;
        $after = array_search($object, $objects, true);
        $following = is_int($after) ? ($objects[$after + 1] ?? null) : null;

        return $following !== null
            ? new SyncPage($records, $removed, (string) json_encode(['object' => $following, 'marks' => $marks, 'newest' => $newest]), false)
            : new SyncPage($records, $removed, (string) json_encode(['marks' => $marks]), true);
    }

    public function requestsPerMinute(): int
    {
        // Orgs get at least 100,000 API calls a day; REOS stays far under that.
        return 120;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        // No push without Change Data Capture (paid editions): polling keeps REOS current.
        return null;
    }

    public function createNote(Connection $connection, string $token, string $personId, string $body): string
    {
        [$instance] = self::split($token);
        $base = $instance.'/services/data/'.self::VERSION.'/sobjects';
        $note = $this->json($this->http($token)->post($base.'/ContentNote', [
            'Title' => mb_substr((string) strtok(trim($body), "\n"), 0, 80) ?: 'REOS note',
            'Content' => base64_encode(nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_HTML5), false)),
        ]));
        $id = $note['id'] ?? null;

        if (! is_string($id)) {
            throw new VendorUnavailable('Salesforce returned no note id.');
        }

        // A ContentNote's id is its document's: link it to the person, visible to them.
        $this->json($this->http($token)->post($base.'/ContentDocumentLink', ['ContentDocumentId' => $id, 'LinkedEntityId' => $personId, 'ShareType' => 'V']));

        return $id;
    }

    /**
     * A Task on the person (`WhoId`), owned by the person's owner so it comes
     * back to the same agent (B8.2's owner rule).
     */
    public function createTask(Connection $connection, string $token, string $personId, string $title, ?CarbonImmutable $due): string
    {
        [$instance] = self::split($token);
        $base = $instance.'/services/data/'.self::VERSION.'/sobjects';
        $object = str_starts_with($personId, '00Q') ? 'Lead' : 'Contact';
        $person = $this->json($this->http($token)->get($base.'/'.$object.'/'.rawurlencode($personId), ['fields' => 'OwnerId']));
        $task = $this->json($this->http($token)->post($base.'/Task', array_filter([
            'WhoId' => $personId, 'Subject' => mb_substr($title, 0, 255), 'Status' => 'Not Started', 'ActivityDate' => $due?->toDateString(),
            'OwnerId' => is_string($person['OwnerId'] ?? null) ? $person['OwnerId'] : null,
        ], static fn (mixed $value): bool => $value !== null)));
        $id = $task['id'] ?? null;

        return is_string($id) ? $id : throw new VendorUnavailable('Salesforce returned no task id.');
    }

    public function setTaskDone(Connection $connection, string $token, string $taskId, bool $done): void
    {
        [$instance] = self::split($token);
        $this->json($this->http($token)->patch($instance.'/services/data/'.self::VERSION.'/sobjects/Task/'.rawurlencode($taskId), ['Status' => $done ? 'Completed' : 'Not Started']));
    }

    public function updatePerson(Connection $connection, string $token, string $personId, array $fields): void
    {
        $map = SalesforceRecords::mapping($connection->meta);
        $lead = str_starts_with($personId, SalesforceRecords::LEAD);
        $field = $lead ? $map['lead_stage'] : $map['contact_stage'];

        // Salesforce has no standard tags; a stage goes only where the brokerage mapped one.
        if ($field === null || ! isset($fields['stage'])) {
            return;
        }

        [$instance] = self::split($token);
        $this->json($this->http($token)->patch($instance.'/services/data/'.self::VERSION.'/sobjects/'.($lead ? 'Lead' : 'Contact').'/'.rawurlencode($personId), [$field => $fields['stage']]));
    }

    /**
     * The fields screen 40 can map a stage to: picklists and short text on
     * Contact and Lead, standard and custom.
     *
     * @return array{Contact: list<array{name: string, label: string}>, Lead: list<array{name: string, label: string}>}
     */
    public function stageFields(string $token): array
    {
        [$instance] = self::split($token);
        $fields = ['Contact' => [], 'Lead' => []];

        foreach (array_keys($fields) as $object) {
            $describe = $this->json($this->http($token)->get($instance.'/services/data/'.self::VERSION.'/sobjects/'.$object.'/describe'));
            foreach (is_array($describe['fields'] ?? null) ? $describe['fields'] : [] as $f) {
                if (is_array($f) && is_string($f['name'] ?? null) && in_array($f['type'] ?? null, ['picklist', 'string'], true) && ($f['updateable'] ?? false) === true) {
                    $fields[$object][] = ['name' => $f['name'], 'label' => is_string($f['label'] ?? null) ? $f['label'] : $f['name']];
                }
            }
        }

        return $fields;
    }

    /**
     * @param  array{contact_stage: string|null, lead_stage: string, leads: bool}  $map
     */
    public static function soql(string $object, array $map, ?string $mark): string
    {
        $since = $mark === null ? '' : ' AND SystemModstamp > '.self::datetime($mark);

        return match ($object) {
            'Contact' => 'SELECT Id, FirstName, LastName, Email, Phone, MobilePhone, Birthdate, Owner.Email, SystemModstamp'.($map['contact_stage'] !== null ? ', '.$map['contact_stage'] : '')
                .' FROM Contact WHERE IsDeleted = false'.$since.' ORDER BY SystemModstamp, Id',
            'Lead' => 'SELECT Id, FirstName, LastName, Email, Phone, MobilePhone, '.$map['lead_stage'].', Owner.Email, SystemModstamp FROM Lead WHERE IsConverted = false'.$since.' ORDER BY SystemModstamp, Id',
            'ContentNote' => 'SELECT Id, Title, TextPreview, LastModifiedDate FROM ContentNote'.($mark === null ? '' : ' WHERE LastModifiedDate > '.self::datetime($mark)).' ORDER BY LastModifiedDate, Id',
            default => 'SELECT Id, Subject, ActivityDate, Status, IsClosed, WhoId, Owner.Email, SystemModstamp FROM Task WHERE WhoId != null'.$since.' ORDER BY SystemModstamp, Id',
        };
    }

    /**
     * Notes with the person each is linked to (a Contact or Lead), and the
     * full text when the preview was cut short.
     *
     * @param  list<array<array-key, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function notes(string $token, array $rows): array
    {
        $ids = array_values(array_filter(array_map(static fn (array $r): ?string => is_string($r['Id'] ?? null) && preg_match('/^[A-Za-z0-9]{15,18}$/', $r['Id']) === 1 ? $r['Id'] : null, $rows)));

        if ($ids === []) {
            return [];
        }

        [$instance] = self::split($token);
        $links = $this->json($this->http($token)->get($instance.'/services/data/'.self::VERSION.'/query', [
            'q' => "SELECT ContentDocumentId, LinkedEntityId FROM ContentDocumentLink WHERE ContentDocumentId IN ('".implode("','", $ids)."')",
        ]));
        $people = [];
        foreach (is_array($links['records'] ?? null) ? $links['records'] : [] as $link) {
            $entity = is_array($link) && is_string($link['LinkedEntityId'] ?? null) ? $link['LinkedEntityId'] : '';
            if (is_array($link) && is_string($link['ContentDocumentId'] ?? null) && (str_starts_with($entity, SalesforceRecords::CONTACT) || str_starts_with($entity, SalesforceRecords::LEAD))) {
                $people[$link['ContentDocumentId']] = $entity;
            }
        }

        $records = [];
        foreach ($rows as $row) {
            $id = is_string($row['Id'] ?? null) ? $row['Id'] : '';
            if (! isset($people[$id])) {
                continue;
            }
            $preview = is_string($row['TextPreview'] ?? null) ? $row['TextPreview'] : '';
            $content = mb_strlen($preview) >= self::PREVIEW ? $this->content($token, $id) : null;
            $records[] = SalesforceRecords::note($row, $people[$id], $content);
        }

        return $records;
    }

    private function content(string $token, string $id): ?string
    {
        [$instance] = self::split($token);
        $response = $this->http($token)->get($instance.'/services/data/'.self::VERSION.'/sobjects/ContentNote/'.rawurlencode($id).'/Content');

        return $response->successful() ? trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $response->body())), ENT_QUOTES | ENT_HTML5)) : null;
    }

    /** @return list<string> ids of people deleted since the mark */
    private function deleted(string $token, string $object, string $mark): array
    {
        [$instance] = self::split($token);
        $start = CarbonImmutable::parse($mark)->max(CarbonImmutable::now()->subDays(14));
        $body = $this->json($this->http($token)->get($instance.'/services/data/'.self::VERSION.'/sobjects/'.$object.'/deleted/', [
            'start' => $start->utc()->format('Y-m-d\TH:i:s\Z'), 'end' => CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]));
        $ids = [];

        foreach (is_array($body['deletedRecords'] ?? null) ? $body['deletedRecords'] : [] as $record) {
            if (is_array($record) && is_string($record['id'] ?? null)) {
                $ids[] = $record['id'];
            }
        }

        return $ids;
    }

    /** @param  array<array-key, mixed>  $body */
    private function tokens(array $body, ?string $refreshToken): TokenSet
    {
        $access = $body['access_token'] ?? null;
        $instance = $body['instance_url'] ?? null;

        if (! is_string($access) || ! is_string($instance) || preg_match('#^https://[A-Za-z0-9.-]+$#', rtrim($instance, '/')) !== 1) {
            throw new AccessRevoked('Salesforce returned no session.');
        }

        $refresh = is_string($body['refresh_token'] ?? null) ? $body['refresh_token'] : $refreshToken;
        $scopes = is_string($body['scope'] ?? null) ? array_values(array_filter(explode(' ', $body['scope']))) : [];

        return new TokenSet(rtrim($instance, '/').' '.$access, $refresh, CarbonImmutable::now()->addMinutes(self::SESSION_MINUTES), $scopes);
    }

    /** @return array{0: string, 1: string} the org's address and the session token */
    public static function split(string $token): array
    {
        $parts = explode(' ', $token, 2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : ['', $token];
    }

    private static function datetime(string $iso): string
    {
        return CarbonImmutable::parse($iso)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function login(): string
    {
        return rtrim($this->loginUrl !== '' ? $this->loginUrl : self::LOGIN, '/');
    }

    private function http(string $token): PendingRequest
    {
        return Http::withToken(self::split($token)[1])->acceptJson()->timeout(30)->connectTimeout(5);
    }

    /** @return array<array-key, mixed> */
    private function json(Response|PromiseInterface $response): array
    {
        if (! $response instanceof Response) {
            throw new VendorUnavailable('Salesforce did not answer.');
        }

        $first = $response->json('0.errorCode');

        return match (true) {
            $response->status() === 401 || in_array($response->json('error'), ['invalid_grant', 'invalid_client'], true) => throw new AccessRevoked('Salesforce ended the session.'),
            $response->status() === 403 && $first === 'REQUEST_LIMIT_EXCEEDED' => throw new VendorRateLimited(3600),
            $response->status() === 429 => throw new VendorRateLimited(60),
            $response->failed() => throw new VendorUnavailable('Salesforce answered '.$response->status().(is_string($first) ? ' '.$first : '').'.'),
            default => (array) $response->json(),
        };
    }
}
