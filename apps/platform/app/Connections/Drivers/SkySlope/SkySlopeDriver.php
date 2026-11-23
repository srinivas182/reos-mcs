<?php

declare(strict_types=1);

namespace App\Connections\Drivers\SkySlope;

use App\Connections\AcceptsDocuments;
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
use App\Models\Deal;
use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LogicException;

/**
 * SkySlope (B9.2, ADR 0037). The brokerage connects once on screen 40 with
 * a broker or admin user's access key and secret, which reach every file in
 * the brokerage; REOS adds its own client ID and secret (from SkySlope's
 * customer success team) and signs the login with HMAC-SHA256. Sessions
 * last two hours.
 *
 * - Read, every 15 minutes: sales and listings changed since the last
 *   sync, 10 a page, with their key dates (escrow closing, listing
 *   expiry). Each file goes to the brokerage agent whose email it carries.
 * - SkySlope's published API has no document list or checklist read and no
 *   webhooks; the signed document from B9.3 is added to the sale or
 *   listing (to confirm in the partner sandbox).
 */
final readonly class SkySlopeDriver implements AcceptsDocuments, ConnectorDriver, ConnectsWithApiKey
{
    public const INTEGRATION = 'skyslope';

    public const STREAMS = ['deals' => 'read'];

    /** @var array{can: list<string>, never: list<string>} */
    public const CONSENT = ['can' => ['read_deals'], 'never' => ['change_deals', 'delete_documents']];

    public const API = 'https://api.skyslope.com';

    public function __construct(private string $clientId, private string $clientSecret, private string $api = self::API) {}

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    /** The broker's "access key:secret", checked by logging in. */
    public function accountForKey(string $apiKey): ConnectedAccount
    {
        [$key] = self::split($apiKey);
        $this->session($apiKey, fresh: true);

        return new ConnectedAccount($key, 'SkySlope ('.substr($key, 0, 6).'…)');
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

    public function revoke(string $token): void
    {
        Cache::forget(self::sessionKey($token));
    }

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
        $state = is_string($cursor) ? (array) json_decode($cursor, true) : [];
        $kind = ($state['kind'] ?? 'sale') === 'listing' ? 'listing' : 'sale';
        $page = is_int($state['page'] ?? null) ? $state['page'] : 1;
        $from = is_int($state['from'] ?? null) ? $state['from'] : $since?->getTimestamp();
        $started = is_int($state['started'] ?? null) ? $state['started'] : CarbonImmutable::now()->getTimestamp();
        $query = ['pageNumber' => $page];
        if ($from !== null) {
            $query['earliestDate'] = $from;
            $query['latestDate'] = $started;
        }

        $body = $this->json($this->http($accessToken)->get($this->base().'/api/files/'.($kind === 'sale' ? 'sales' : 'listings'), $query));
        $value = $body['value'] ?? $body;
        $list = is_array($value) ? ($value[$kind === 'sale' ? 'sales' : 'listings'] ?? $value) : [];
        $records = [];
        foreach (is_array($list) ? $list : [] as $file) {
            if (is_array($file)) {
                $records[] = SkySlopeRecords::deal($file, $kind);
            }
        }

        $links = is_array($body['links'] ?? null) ? $body['links'] : [];
        $more = ($links['next'] ?? null) !== null && $records !== [];

        return match (true) {
            $more => new SyncPage($records, [], (string) json_encode(['kind' => $kind, 'page' => $page + 1, 'from' => $from, 'started' => $started]), false),
            $kind === 'sale' => new SyncPage($records, [], (string) json_encode(['kind' => 'listing', 'page' => 1, 'from' => $from, 'started' => $started]), false),
            default => new SyncPage($records, [], (string) json_encode(['from' => $started]), true),
        };
    }

    public function requestsPerMinute(): int
    {
        // 100 a minute per client ID, shared by every brokerage.
        return 90;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        return null;
    }

    /** The signed copy onto the sale or listing (B9.3). */
    public function uploadDocument(Connection $connection, string $accessToken, Deal $deal, string $name, string $contents): bool
    {
        [$kind, $guid] = array_pad(explode(':', (string) $deal->external_id, 2), 2, '');

        if (! in_array($kind, ['sale', 'listing'], true) || $guid === '') {
            return false;
        }

        $this->json($this->http($accessToken)->timeout(120)->post($this->base().'/api/files/'.($kind === 'sale' ? 'sales' : 'listings').'/'.rawurlencode($guid).'/documents', [
            'fileName' => $name, 'document' => base64_encode($contents),
        ]));

        return true;
    }

    /** A two-hour session for this brokerage's key, logged in with HMAC-SHA256. */
    private function session(string $token, bool $fresh = false): string
    {
        $cached = $fresh ? null : Cache::get(self::sessionKey($token));

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        [$key, $secret] = self::split($token);
        $timestamp = CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s\Z');
        $hmac = base64_encode(hash_hmac('sha256', $this->clientId.':'.$this->clientSecret.':'.$timestamp, $secret, true));
        $body = $this->json(Http::acceptJson()->asJson()->timeout(30)->withHeaders(['Authorization' => 'SS '.$key.':'.$hmac, 'Timestamp' => $timestamp])
            ->post($this->base().'/auth/login', ['clientID' => $this->clientId, 'clientSecret' => $this->clientSecret]));
        $session = $body['Session'] ?? $body['session'] ?? null;

        if (! is_string($session) || $session === '') {
            throw new AccessRevoked('SkySlope gave no session.');
        }

        Cache::put(self::sessionKey($token), $session, 100 * 60);

        return $session;
    }

    private function http(string $token): PendingRequest
    {
        return Http::withHeaders(['Session' => $this->session($token)])->acceptJson()->timeout(30);
    }

    /** @return array{0: string, 1: string} access key and secret */
    public static function split(string $token): array
    {
        $parts = explode(':', trim($token), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new AccessRevoked('A SkySlope access key and secret are needed.');
        }

        return [$parts[0], $parts[1]];
    }

    private function base(): string
    {
        return rtrim($this->api !== '' ? $this->api : self::API, '/');
    }

    private static function sessionKey(string $token): string
    {
        return 'skyslope-session:'.sha1($token);
    }

    /** @return array<array-key, mixed> */
    private function json(Response|PromiseInterface $response): array
    {
        if (! $response instanceof Response) {
            throw new VendorUnavailable('SkySlope did not answer.');
        }

        return match (true) {
            $response->status() === 401, $response->status() === 403 => throw new AccessRevoked('SkySlope refused the key.'),
            $response->status() === 429 => throw new VendorRateLimited(60),
            $response->failed() => throw new VendorUnavailable('SkySlope answered '.$response->status().'.'),
            default => (array) $response->json(),
        };
    }
}
