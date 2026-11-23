<?php

declare(strict_types=1);

namespace App\Connections\Drivers\FollowUpBoss;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectsWithApiKey;
use App\Connections\Crm\CrmDriver;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\Stores\ContactNoteStore;
use App\Connections\Stores\ContactStore;
use App\Connections\Stores\ContactTaskStore;
use App\Connections\SubscribesToChanges;
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
use LogicException;

/**
 * Follow Up Boss (B7.2, ADR 0028). Agents connect with their own API key;
 * every request also carries REOS's registered system name and key
 * (Settings › Integrations). People, notes and tasks are read in pages,
 * newest change first, from where the last sync stopped; notes, task
 * completion, stages and tags are written back. Follow Up Boss's webhooks
 * (signed with the system key) say when to sync; the 15-minute schedule is
 * the backstop.
 */
final readonly class FollowUpBossDriver implements ConnectorDriver, ConnectsWithApiKey, CrmDriver, SubscribesToChanges
{
    public const INTEGRATION = 'follow-up-boss';

    public const BASE = 'https://api.followupboss.com/v1';

    public const STREAMS = ['people' => 'both', 'notes' => 'both', 'tasks' => 'both'];

    /** @var array{can: list<string>, never: list<string>} */
    public const CONSENT = ['can' => ['read_people', 'write_notes', 'write_tasks', 'write_stage'], 'never' => ['delete_people', 'send_messages']];

    /** Follow Up Boss's webhook events REOS listens to. */
    public const EVENTS = ['peopleCreated', 'peopleUpdated', 'peopleDeleted', 'peopleTagsCreated', 'notesCreated', 'notesUpdated', 'tasksCreated', 'tasksUpdated'];

    private const PAGE = 100;

    private const LISTS = ['people' => 'people', 'notes' => 'notes', 'tasks' => 'tasks'];

    public function __construct(private string $systemName, private string $systemKey) {}

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public function accountForKey(string $apiKey): ConnectedAccount
    {
        $me = $this->json($this->http($apiKey)->get(self::BASE.'/me'));
        $id = $me['id'] ?? null;
        $account = is_array($me['account'] ?? null) ? $me['account'] : [];
        $accountId = $account['id'] ?? $me['accountId'] ?? null;

        if (! is_scalar($id)) {
            throw new AccessRevoked('Follow Up Boss returned no user.');
        }

        $label = is_string($me['email'] ?? null) ? $me['email'] : (is_string($me['name'] ?? null) ? $me['name'] : null);

        return new ConnectedAccount((is_scalar($accountId) ? $accountId.':' : '').$id, $label);
    }

    public function streams(): array
    {
        return ['people' => ContactStore::class, 'notes' => ContactNoteStore::class, 'tasks' => ContactTaskStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $list = self::LISTS[$stream] ?? throw new LogicException("Unknown stream {$stream}.");
        $state = is_string($cursor) ? (array) json_decode($cursor, true) : [];
        $after = is_string($state['since'] ?? null) ? $state['since'] : $since?->toIso8601String();
        $query = array_filter([
            'limit' => self::PAGE, 'sort' => 'updated',
            'updatedAfter' => $after,
            'next' => is_string($state['next'] ?? null) ? $state['next'] : null,
        ], static fn (mixed $v): bool => $v !== null);

        $body = $this->json($this->http($accessToken)->get(self::BASE.'/'.$list, $query));
        $rows = is_array($body[$list] ?? null) ? array_values(array_filter($body[$list], is_array(...))) : [];
        $records = array_map(fn (array $row): array => match ($stream) {
            'people' => FollowUpBossRecords::person($row),
            'notes' => FollowUpBossRecords::note($row),
            default => FollowUpBossRecords::task($row),
        }, $rows);
        $meta = is_array($body['_metadata'] ?? null) ? $body['_metadata'] : [];
        $next = is_string($meta['next'] ?? null) && $meta['next'] !== '' ? $meta['next'] : null;
        $newest = $state['newest'] ?? $after;

        foreach ($records as $record) {
            $updated = $record['updated_at'] ?? null;
            if (is_string($updated) && (! is_string($newest) || $updated > $newest)) {
                $newest = $updated;
            }
        }

        $removed = [];
        if ($stream === 'people') {
            // Follow Up Boss keeps deleted people out of lists; webhooks name them (peopleDeleted).
            $removed = array_values(array_filter(array_map(static fn (mixed $id): string => is_scalar($id) ? (string) $id : '', (array) ($connection->meta['deleted_people'] ?? []))));
        }

        return $next !== null
            ? new SyncPage($records, $removed, (string) json_encode(['since' => $after, 'next' => $next, 'newest' => $newest]), false)
            : new SyncPage($records, $removed, (string) json_encode(['since' => is_string($newest) ? $newest : $after]), true);
    }

    public function requestsPerMinute(): int
    {
        // Follow Up Boss allows bursts per 10 seconds; REOS stays well under it.
        return 300;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        $body = $request->getContent();
        $signature = $request->header('FUB-Signature');

        if (! is_string($signature) || $this->systemKey === '' || ! hash_equals(hash_hmac('sha256', base64_encode($body), $this->systemKey), $signature)) {
            return null;
        }

        $payload = (array) json_decode($body, true);
        $event = $payload['eventId'] ?? null;
        $connection = WebhookTarget::open($request->query('c'));

        if (! is_string($event) || $connection === null) {
            return null;
        }

        if (($payload['event'] ?? null) === 'peopleDeleted') {
            WebhookTarget::remember($connection, (array) ($payload['resourceIds'] ?? []));
        }

        return new WebhookDelivery('fub:'.$event, [$connection]);
    }

    public function watchedResources(Connection $connection): array
    {
        return self::EVENTS;
    }

    public function subscribe(Connection $connection, string $accessToken, string $webhookUrl, string $resource): array
    {
        $hook = $this->json($this->http($accessToken)->post(self::BASE.'/webhooks', ['event' => $resource, 'url' => $webhookUrl.'?c='.WebhookTarget::seal($connection->id)]));
        $id = $hook['id'] ?? null;

        return ['id' => is_scalar($id) ? (string) $id : '', 'resource_id' => null, 'expires_at' => null];
    }

    public function unsubscribe(Connection $connection, string $accessToken, string $channelId, ?string $resourceId): void
    {
        $this->http($accessToken)->delete(self::BASE.'/webhooks/'.rawurlencode($channelId));
    }

    public function createNote(Connection $connection, string $token, string $personId, string $body): string
    {
        $note = $this->json($this->http($token)->post(self::BASE.'/notes', ['personId' => (int) $personId, 'subject' => 'REOS', 'body' => $body]));
        $id = $note['id'] ?? null;

        return is_scalar($id) ? (string) $id : throw new VendorUnavailable('Follow Up Boss returned no note id.');
    }

    public function setTaskDone(Connection $connection, string $token, string $taskId, bool $done): void
    {
        $this->json($this->http($token)->put(self::BASE.'/tasks/'.rawurlencode($taskId), ['isCompleted' => $done]));
    }

    public function updatePerson(Connection $connection, string $token, string $personId, array $fields): void
    {
        $this->json($this->http($token)->put(self::BASE.'/people/'.rawurlencode($personId), array_filter(['stage' => $fields['stage'] ?? null, 'tags' => $fields['tags'] ?? null], static fn (mixed $v): bool => $v !== null)));
    }

    // Key-based: there's no sign-in page, code exchange or refresh.
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

    private function http(string $apiKey): PendingRequest
    {
        return Http::withBasicAuth($apiKey, '')
            ->withHeaders(['X-System' => $this->systemName !== '' ? $this->systemName : 'REOS', 'X-System-Key' => $this->systemKey, 'Accept' => 'application/json'])
            ->timeout(30)->connectTimeout(5);
    }

    /** @return array<array-key, mixed> */
    private function json(Response|PromiseInterface $response): array
    {
        if (! $response instanceof Response) {
            throw new VendorUnavailable('Follow Up Boss did not answer.');
        }

        return match (true) {
            $response->status() === 401 || $response->status() === 403 => throw new AccessRevoked('Follow Up Boss refused the key.'),
            $response->status() === 429 => throw new VendorRateLimited((int) ($response->header('Retry-After') !== '' ? $response->header('Retry-After') : 60)),
            $response->failed() => throw new VendorUnavailable('Follow Up Boss answered '.$response->status().'.'),
            default => (array) $response->json(),
        };
    }
}
