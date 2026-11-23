<?php

declare(strict_types=1);

namespace App\Connections\Drivers\LoneWolf;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectsWithApiKey;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\Stores\DealStore;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LogicException;

/**
 * Lone Wolf Transact (B9.1b, ADR 0037). The brokerage connects once, from
 * Brokerage integrations (screen 40), with its Lone Wolf client ID; REOS
 * signs in with its partner keys (OAuth 2 client credentials, plus the
 * subscription key on every request), scoped to that client.
 *
 * - Read, every 15 minutes: the client's users, then each user's
 *   transactions with their contacts, documents and offers, as deals.
 *   Each transaction goes to the agent whose email is the Transact user's;
 *   the rest are counted as unmatched (screen 40).
 * - A transaction gone from Transact is removed in REOS at the end of a
 *   full read.
 * - Read-only (decision 3). Document upload (the signed copy from B9.3) is
 *   added where the partner terms allow it.
 * - Transact's published API has no webhooks; polling keeps REOS current.
 */
final readonly class LoneWolfDriver implements ConnectorDriver, ConnectsWithApiKey
{
    public const INTEGRATION = 'lone-wolf';

    public const STREAMS = ['deals' => 'read'];

    /** @var array{can: list<string>, never: list<string>} */
    public const CONSENT = ['can' => ['read_deals', 'read_documents'], 'never' => ['change_deals', 'delete_documents']];

    public const API = 'https://api.lwolf.com';

    public function __construct(private string $clientId, private string $clientSecret, private string $subscriptionKey, private string $api = self::API) {}

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    /** The brokerage's Lone Wolf client ID is checked by signing in to it. */
    public function accountForKey(string $apiKey): ConnectedAccount
    {
        $client = trim($apiKey);

        if (preg_match('/^[0-9a-fA-F-]{8,64}$/', $client) !== 1) {
            throw new AccessRevoked('Not a Lone Wolf client ID.');
        }

        $users = $this->json($this->http($client)->get($this->base().'/platform/v1/clients/'.rawurlencode($client).'/users', ['$top' => 1]));
        $name = is_string($users['clientName'] ?? null) ? $users['clientName'] : 'Lone Wolf client '.substr($client, 0, 8);

        return new ConnectedAccount($client, $name);
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

    public function revoke(string $token): void
    {
        Cache::forget(self::tokenKey($token));
    }

    public function account(TokenSet $tokens): ConnectedAccount
    {
        return $this->accountForKey($tokens->accessToken);
    }

    public function streams(): array
    {
        return ['deals' => DealStore::class];
    }

    /**
     * One user's transactions per page. The cursor holds the users still to
     * read, the transactions seen in this pass and those known from the last
     * one, so anything gone from Transact is removed at the end.
     */
    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $state = is_string($cursor) ? (array) json_decode($cursor, true) : [];
        $known = self::ids($state['known'] ?? []);
        $users = is_array($state['users'] ?? null) ? array_values(array_filter($state['users'], is_array(...))) : null;
        $seen = self::ids($state['seen'] ?? []);

        if ($users === null) {
            $list = $this->json($this->http($accessToken)->get($this->base().'/platform/v1/clients/'.rawurlencode($accessToken).'/users'));
            $users = [];
            foreach (self::rows($list) as $user) {
                $id = $user['id'] ?? null;
                if (is_string($id) || is_int($id)) {
                    $users[] = ['id' => (string) $id, 'email' => is_string($user['email'] ?? null) ? $user['email'] : null];
                }
            }
        }

        $user = array_shift($users);
        $records = [];

        if (is_array($user) && is_string($user['id'] ?? null)) {
            $base = $this->base().'/transact-workflow/v1/users/'.rawurlencode($user['id']);
            foreach (self::rows($this->json($this->http($accessToken)->get($base.'/transactions'))) as $tx) {
                $id = $tx['id'] ?? null;
                if (! is_string($id) && ! is_int($id)) {
                    continue;
                }
                $filter = ['$filter' => 'transactionId eq '.$id];
                $records[] = LoneWolfRecords::deal(
                    [...$tx, 'id' => (string) $id],
                    self::rows($this->json($this->http($accessToken)->get($base.'/Contacts', $filter))),
                    self::rows($this->json($this->http($accessToken)->get($base.'/documents', $filter))),
                    self::rows($this->json($this->http($accessToken)->get($base.'/Offers', $filter))),
                    is_string($user['email'] ?? null) ? $user['email'] : null,
                );
                $seen[] = (string) $id;
            }
        }

        if ($users !== []) {
            return new SyncPage($records, [], (string) json_encode(['users' => $users, 'seen' => $seen, 'known' => $known]), false);
        }

        return new SyncPage($records, array_values(array_diff($known, $seen)), (string) json_encode(['known' => array_values(array_unique($seen))]), true);
    }

    public function requestsPerMinute(): int
    {
        return 60;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        return null;
    }

    /** A bearer token for the brokerage's client, kept until just before it expires. */
    private function token(string $client): string
    {
        $cached = Cache::get(self::tokenKey($client));

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $body = $this->json(Http::asJson()->acceptJson()->timeout(30)->post($this->base().'/oauth/token', [
            'grant_type' => 'client_credentials', 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'audience' => self::API, 'lwt_client_id' => $client,
        ]));
        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new AccessRevoked('Lone Wolf gave no token.');
        }

        $seconds = is_numeric($body['expires_in'] ?? null) ? (int) $body['expires_in'] : 3600;
        Cache::put(self::tokenKey($client), $token, max(60, $seconds - 120));

        return $token;
    }

    private function http(string $client): PendingRequest
    {
        return Http::withToken($this->token($client))->withHeaders(['lw-subscription-key' => $this->subscriptionKey])->acceptJson()->timeout(30);
    }

    private function base(): string
    {
        return rtrim($this->api !== '' ? $this->api : self::API, '/');
    }

    private static function tokenKey(string $client): string
    {
        return 'lone-wolf-token:'.sha1($client);
    }

    /**
     * Rows of a list answer: a bare list, or under `value`, `items` or `data`.
     *
     * @param  array<array-key, mixed>  $body
     * @return list<array<array-key, mixed>>
     */
    private static function rows(array $body): array
    {
        $list = array_is_list($body) ? $body : ($body['value'] ?? $body['items'] ?? $body['data'] ?? []);

        return is_array($list) ? array_values(array_filter($list, is_array(...))) : [];
    }

    /** @return list<string> */
    private static function ids(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    /** @return array<array-key, mixed> */
    private function json(Response|PromiseInterface $response): array
    {
        if (! $response instanceof Response) {
            throw new VendorUnavailable('Lone Wolf did not answer.');
        }

        return match (true) {
            $response->status() === 401, $response->status() === 403 => throw new AccessRevoked('Lone Wolf refused REOS\'s access.'),
            $response->status() === 429 => throw new VendorRateLimited(60),
            $response->failed() => throw new VendorUnavailable('Lone Wolf answered '.$response->status().'.'),
            default => (array) $response->json(),
        };
    }
}
