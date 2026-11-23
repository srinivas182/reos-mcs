<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Lofty;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectsWithApiKey;
use App\Connections\Crm\CrmDriver;
use App\Connections\Drivers\FollowUpBoss\WebhookTarget;
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
 * Lofty (B8.1, ADR 0033). Each agent connects with their own Lofty API key
 * (Settings › Integrations › API in Lofty), sent as `Authorization: token …`.
 *
 * Reading: Lofty's lead list has no "changed since" filter, so a sync pages
 * through the leads (100 at a time) and keeps those changed since the last
 * sync's mark; notes and tasks are read per changed lead. Writing: notes
 * (`POST /v1.0/notes`), task done or reopened (`/v2.0/tasks/{id}/finish`,
 * `/unfinish`), and a lead's stage and tags (`PUT /v1.0/leads/{id}`).
 *
 * Lofty's webhooks (lead, note and task events) only say when to sync. Lofty
 * doesn't sign them, so REOS gives each connection its own signed address
 * and trusts nothing in the body beyond the ids of deleted leads; the
 * 15-minute schedule is the backstop. Every request asks for real HTTP error
 * statuses (`X-Error-Status: true`).
 */
final readonly class LoftyDriver implements ConnectorDriver, ConnectsWithApiKey, CrmDriver, SubscribesToChanges
{
    public const INTEGRATION = 'lofty';

    public const BASE = 'https://api.lofty.com';

    public const STREAMS = ['people' => 'both', 'notes' => 'both', 'tasks' => 'both'];

    /** @var array{can: list<string>, never: list<string>} */
    public const CONSENT = ['can' => ['read_people', 'write_notes', 'write_tasks', 'write_stage'], 'never' => ['delete_people', 'send_messages']];

    /** Lofty's webhook lists: 2 lead info, 9 notes, 10 tasks. */
    public const EVENTS = ['2', '9', '10'];

    /** Webhook addresses are signed for Lofty alone. */
    public const PURPOSE = 'lofty-webhook';

    private const PAGE = 100;

    /** Leads whose notes or tasks are read per page. */
    private const LEADS_PER_PAGE = 20;

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public function accountForKey(string $apiKey): ConnectedAccount
    {
        // A page of one lead proves the key works; the key names the account.
        $this->json($this->http($apiKey)->get(self::BASE.'/v1.0/leads', ['limit' => 1]));

        return new ConnectedAccount('lofty-'.substr(hash('sha256', $apiKey), 0, 16), 'Lofty');
    }

    public function streams(): array
    {
        return ['people' => ContactStore::class, 'notes' => ContactNoteStore::class, 'tasks' => ContactTaskStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $state = is_string($cursor) ? (array) json_decode($cursor, true) : [];
        $mark = is_string($state['mark'] ?? null) ? $state['mark'] : ($since === null ? null : LoftyRecords::time($since->utc()->format('Y-m-d H:i:s')));
        $offset = is_int($state['offset'] ?? null) ? $state['offset'] : 0;
        $newest = is_string($state['newest'] ?? null) ? $state['newest'] : $mark;
        $limit = $stream === 'people' ? self::PAGE : self::LEADS_PER_PAGE;

        $body = $this->json($this->http($accessToken)->get(self::BASE.'/v1.0/leads', ['limit' => $limit, 'offset' => $offset]));
        $leads = array_map(LoftyRecords::person(...), self::rows($body, 'leads'));
        $changed = array_values(array_filter($leads, static fn (array $l): bool => $mark === null || ! is_string($l['updated_at']) || $l['updated_at'] > $mark));
        $records = $changed;

        if ($stream !== 'people') {
            $records = [];
            foreach ($changed as $lead) {
                $id = is_string($lead['external_id']) ? $lead['external_id'] : '';
                $records = [...$records, ...($stream === 'notes'
                    ? array_map(LoftyRecords::note(...), self::rows($this->json($this->http($accessToken)->get(self::BASE.'/v1.0/notes', ['leadId' => $id])), 'notes'))
                    : array_map(LoftyRecords::task(...), self::rows($this->json($this->http($accessToken)->get(self::BASE.'/v1.0/tasks', ['leadId' => $id])), 'taskList')))];
            }
        }

        foreach ($changed as $lead) {
            if (is_string($lead['updated_at']) && (! is_string($newest) || $lead['updated_at'] > $newest)) {
                $newest = $lead['updated_at'];
            }
        }

        // Deleted leads come only from webhooks; the people sync removes them.
        $removed = $stream === 'people' ? array_values(array_filter(array_map(static fn (mixed $id): string => is_scalar($id) ? (string) $id : '', (array) ($connection->meta['deleted_people'] ?? [])))) : [];
        $meta = is_array($body['get_metadata'] ?? null) ? $body['get_metadata'] : [];
        $total = is_int($meta['total'] ?? null) ? $meta['total'] : 0;
        $more = $offset + $limit < $total && count($leads) === $limit;

        return $more
            ? new SyncPage($records, $removed, (string) json_encode(['mark' => $mark, 'offset' => $offset + $limit, 'newest' => $newest]), false)
            : new SyncPage($records, $removed, (string) json_encode(['mark' => $newest ?? $mark]), true);
    }

    public function requestsPerMinute(): int
    {
        // Lofty doesn't publish a limit; REOS stays modest until the test account shows one.
        return 120;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        $connection = WebhookTarget::open($request->query('c'), self::PURPOSE);
        $payload = (array) json_decode($request->getContent(), true);
        $delivery = $request->header('Lofty-Webhook-Id');

        if ($connection === null || ! is_string($delivery) || $delivery === '' || ! in_array(is_scalar($payload['listId'] ?? null) ? (string) $payload['listId'] : '', self::EVENTS, true)) {
            return null;
        }

        $deleted = is_array($payload['deletedLead'] ?? null) ? $payload['deletedLead'] : [];
        if ($deleted !== []) {
            WebhookTarget::remember($connection, array_map(static fn (mixed $l): mixed => is_array($l) ? ($l['leadId'] ?? null) : null, $deleted));
        }

        return new WebhookDelivery('lofty:'.mb_substr($delivery, 0, 100), [$connection]);
    }

    public function watchedResources(Connection $connection): array
    {
        return self::EVENTS;
    }

    public function subscribe(Connection $connection, string $accessToken, string $webhookUrl, string $resource): array
    {
        $hook = $this->json($this->http($accessToken)->post(self::BASE.'/v1.0/webhook', [
            'listId' => (int) $resource, 'callbackUrl' => $webhookUrl.'?c='.WebhookTarget::seal($connection->id, self::PURPOSE),
        ]));
        $id = $hook['subscribeId'] ?? $hook['id'] ?? null;

        return ['id' => is_scalar($id) ? (string) $id : '', 'resource_id' => null, 'expires_at' => null];
    }

    public function unsubscribe(Connection $connection, string $accessToken, string $channelId, ?string $resourceId): void
    {
        $this->http($accessToken)->delete(self::BASE.'/v1.0/webhook/'.rawurlencode($channelId));
    }

    public function createNote(Connection $connection, string $token, string $personId, string $body): string
    {
        $note = $this->json($this->http($token)->post(self::BASE.'/v1.0/notes', ['leadId' => (int) $personId, 'content' => mb_substr($body, 0, 2000), 'isPin' => false]));
        $id = $note['noteId'] ?? null;

        return is_scalar($id) ? (string) $id : throw new VendorUnavailable('Lofty returned no note id.');
    }

    public function setTaskDone(Connection $connection, string $token, string $taskId, bool $done): void
    {
        $this->json($this->http($token)->post(self::BASE.'/v2.0/tasks/'.rawurlencode($taskId).($done ? '/finish' : '/unfinish')));
    }

    public function updatePerson(Connection $connection, string $token, string $personId, array $fields): void
    {
        $body = array_filter(['stage' => isset($fields['stage']) ? mb_substr($fields['stage'], 0, 19) : null, 'tags' => $fields['tags'] ?? null], static fn (mixed $v): bool => $v !== null);

        if ($body !== []) {
            $this->json($this->http($token)->put(self::BASE.'/v1.0/leads/'.rawurlencode($personId), $body));
        }
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

    /**
     * @param  array<array-key, mixed>  $body
     * @return list<array<array-key, mixed>>
     */
    private static function rows(array $body, string $key): array
    {
        return is_array($body[$key] ?? null) ? array_values(array_filter($body[$key], is_array(...))) : [];
    }

    private function http(string $apiKey): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'token '.$apiKey, 'X-Error-Status' => 'true'])->acceptJson()->timeout(30)->connectTimeout(5);
    }

    /** @return array<array-key, mixed> */
    private function json(Response|PromiseInterface $response): array
    {
        if (! $response instanceof Response) {
            throw new VendorUnavailable('Lofty did not answer.');
        }

        return match (true) {
            $response->status() === 401 || $response->status() === 403 => throw new AccessRevoked('Lofty refused the key.'),
            $response->status() === 429 => throw new VendorRateLimited((int) ($response->header('Retry-After') !== '' ? $response->header('Retry-After') : 60)),
            $response->failed() => throw new VendorUnavailable('Lofty answered '.$response->status().'.'),
            default => (array) $response->json(),
        };
    }
}
