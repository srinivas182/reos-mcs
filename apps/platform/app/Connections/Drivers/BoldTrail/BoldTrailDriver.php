<?php

declare(strict_types=1);

namespace App\Connections\Drivers\BoldTrail;

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
 * kvCORE / BoldTrail (B7.3, ADR 0029). Each agent connects with their own
 * API token (Lead Engine › Lead Dropbox). kvCORE has no webhooks for
 * integrators, so REOS polls every 15 minutes: contacts newest change
 * first until the last sync's mark, the notes of contacts that changed, and
 * tasks. Notes, task completion and status go back; the smart campaign is
 * read and shown, never changed.
 */
final readonly class BoldTrailDriver implements ConnectorDriver, ConnectsWithApiKey, CrmDriver
{
    public const INTEGRATION = 'boldtrail';

    public const STREAMS = ['people' => 'both', 'notes' => 'both', 'tasks' => 'both'];

    /** @var array{can: list<string>, never: list<string>} */
    public const CONSENT = ['can' => ['read_people', 'write_notes', 'write_tasks', 'write_stage', 'read_campaigns'], 'never' => ['delete_people', 'send_messages', 'change_campaigns']];

    private const PAGE = 100;

    /** Contacts whose notes are read per page of the notes stream. */
    private const NOTE_CONTACTS = 20;

    public function __construct(private string $baseUrl) {}

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public function accountForKey(string $apiKey): ConnectedAccount
    {
        // The token itself names the account; a page of one contact proves it works.
        $this->json($this->http($apiKey)->get($this->base().'/contacts', ['limit' => 1]));

        return new ConnectedAccount('kv-'.substr(hash('sha256', $apiKey), 0, 16), 'kvCORE');
    }

    public function streams(): array
    {
        return ['people' => ContactStore::class, 'notes' => ContactNoteStore::class, 'tasks' => ContactTaskStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $state = is_string($cursor) ? (array) json_decode($cursor, true) : [];
        $mark = is_string($state['mark'] ?? null) ? $state['mark'] : $since?->toIso8601String();
        $page = is_int($state['page'] ?? null) ? $state['page'] : 1;
        $newest = is_string($state['newest'] ?? null) ? $state['newest'] : $mark;

        if ($stream === 'tasks') {
            $body = $this->json($this->http($accessToken)->get($this->base().'/tasks', ['limit' => self::PAGE, 'page' => $page]));
            $records = array_map(BoldTrailRecords::task(...), self::rows($body));

            return self::page($records, [], $body, $page, $mark, $newest);
        }

        // Contacts, newest change first, until the last sync's mark.
        $limit = $stream === 'notes' ? self::NOTE_CONTACTS : self::PAGE;
        $body = $this->json($this->http($accessToken)->get($this->base().'/contacts', ['limit' => $limit, 'page' => $page, 'sort' => '-date_modified']));
        $people = array_map(BoldTrailRecords::person(...), self::rows($body));
        $fresh = array_values(array_filter($people, static fn (array $p): bool => $mark === null || ! is_string($p['updated_at']) || $p['updated_at'] > $mark));
        $records = $fresh;

        if ($stream === 'notes') {
            $records = [];
            foreach ($fresh as $person) {
                $id = is_string($person['external_id']) ? $person['external_id'] : '';
                $notes = $this->json($this->http($accessToken)->get($this->base().'/contact/'.rawurlencode($id).'/notes'));
                foreach (self::rows($notes) as $note) {
                    $records[] = BoldTrailRecords::note($note, $id);
                }
            }
        }

        foreach ($fresh as $person) {
            if (is_string($person['updated_at']) && (! is_string($newest) || $person['updated_at'] > $newest)) {
                $newest = $person['updated_at'];
            }
        }

        // Older than the mark from here on: this sync is done.
        $reachedMark = count($fresh) < count($people);

        return self::page($records, [], $body, $page, $mark, $newest, $reachedMark);
    }

    public function requestsPerMinute(): int
    {
        return 120;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        // kvCORE doesn't push to integrators; polling keeps REOS up to date.
        return null;
    }

    public function createNote(Connection $connection, string $token, string $personId, string $body): string
    {
        $note = $this->json($this->http($token)->post($this->base().'/contact/'.rawurlencode($personId).'/action/note', ['details' => $body]));
        $data = is_array($note['data'] ?? null) ? $note['data'] : $note;
        $id = $data['id'] ?? null;

        return is_scalar($id) ? (string) $id : throw new VendorUnavailable('kvCORE returned no note id.');
    }

    public function setTaskDone(Connection $connection, string $token, string $taskId, bool $done): void
    {
        $this->json($this->http($token)->put($this->base().'/task/'.rawurlencode($taskId), ['completed' => $done ? 1 : 0]));
    }

    public function updatePerson(Connection $connection, string $token, string $personId, array $fields): void
    {
        $status = isset($fields['stage']) ? BoldTrailRecords::status($fields['stage']) : null;

        if ($status !== null) {
            $this->json($this->http($token)->put($this->base().'/contact/'.rawurlencode($personId), ['status' => $status]));
        }
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

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $removed
     * @param  array<array-key, mixed>  $body
     */
    private static function page(array $records, array $removed, array $body, int $page, ?string $mark, ?string $newest, bool $reachedMark = false): SyncPage
    {
        $last = $body['last_page'] ?? null;
        $more = ! $reachedMark && is_int($last) && $page < $last;

        return $more
            ? new SyncPage($records, $removed, (string) json_encode(['mark' => $mark, 'page' => $page + 1, 'newest' => $newest]), false)
            : new SyncPage($records, $removed, (string) json_encode(['mark' => $newest ?? $mark]), true);
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return list<array<array-key, mixed>>
     */
    private static function rows(array $body): array
    {
        return is_array($body['data'] ?? null) ? array_values(array_filter($body['data'], is_array(...))) : [];
    }

    private function base(): string
    {
        return rtrim($this->baseUrl !== '' ? $this->baseUrl : 'https://api.kvcore.com/v2/public', '/');
    }

    private function http(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(30)->connectTimeout(5);
    }

    /** @return array<array-key, mixed> */
    private function json(Response|PromiseInterface $response): array
    {
        if (! $response instanceof Response) {
            throw new VendorUnavailable('kvCORE did not answer.');
        }

        return match (true) {
            $response->status() === 401 || $response->status() === 403 => throw new AccessRevoked('kvCORE refused the token.'),
            $response->status() === 429 => throw new VendorRateLimited(60),
            $response->failed() => throw new VendorUnavailable('kvCORE answered '.$response->status().'.'),
            default => (array) $response->json(),
        };
    }
}
