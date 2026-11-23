<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Dotloop;

use App\Connections\AcceptsDocuments;
use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Drivers\FollowUpBoss\WebhookTarget;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\Stores\DealStore;
use App\Connections\SubscribesToChanges;
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
use Illuminate\Support\Facades\Http;

/**
 * dotloop (B9.2, ADR 0037). The agent signs in with their own dotloop
 * account (OAuth 2, about 12-hour tokens with refresh).
 *
 * - Read: the profiles the agent chose (all by default), then each
 *   profile's loops with their details (property, financials, contract
 *   dates), participants, folders with documents and task lists, as deals.
 * - Live: dotloop's webhooks for loop and participant events on each chosen
 *   profile, signed with a key REOS gives each subscription (HMAC-SHA1 over
 *   "timestamp.body", hex), each handled once; a 15-minute poll is the
 *   backstop.
 * - Read-only (decision 3); the signed document from B9.3 is uploaded into
 *   the loop's folder.
 */
final readonly class DotloopDriver implements AcceptsDocuments, ConnectorDriver, SubscribesToChanges
{
    public const INTEGRATION = 'dotloop';

    public const STREAMS = ['deals' => 'read'];

    /** @var array{can: list<string>, never: list<string>} */
    public const CONSENT = ['can' => ['read_deals', 'read_documents', 'change_alerts'], 'never' => ['change_deals', 'delete_documents']];

    public const AUTH = 'https://auth.dotloop.com/oauth';

    public const API = 'https://api-gateway.dotloop.com/public/v2';

    public const EVENTS = ['LOOP_CREATED', 'LOOP_UPDATED', 'LOOP_MERGED', 'LOOP_PARTICIPANT_CREATED', 'LOOP_PARTICIPANT_UPDATED', 'LOOP_PARTICIPANT_DELETED'];

    private const PURPOSE = 'dotloop-webhook';

    /** A webhook older than this is refused (replays). */
    private const WEBHOOK_SECONDS = 300;

    public function __construct(private string $clientId, private string $clientSecret) {}

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return self::AUTH.'/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => $this->clientId, 'redirect_uri' => $redirectUri, 'state' => $state, 'redirect_on_deny' => 'true']);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        return $this->tokens($this->json(Http::asForm()->withBasicAuth($this->clientId, $this->clientSecret)->acceptJson()->timeout(30)
            ->post(self::AUTH.'/token?'.http_build_query(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri, 'state' => 'reos']))), null);
    }

    public function refresh(string $refreshToken): TokenSet
    {
        return $this->tokens($this->json(Http::asForm()->withBasicAuth($this->clientId, $this->clientSecret)->acceptJson()->timeout(30)
            ->post(self::AUTH.'/token?'.http_build_query(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]))), $refreshToken);
    }

    public function revoke(string $token): void
    {
        rescue(fn () => Http::withBasicAuth($this->clientId, $this->clientSecret)->timeout(10)->post(self::AUTH.'/token/revoke?'.http_build_query(['token' => $token])), report: false);
    }

    public function account(TokenSet $tokens): ConnectedAccount
    {
        $me = $this->json($this->http($tokens->accessToken)->get(self::API.'/account'));
        $data = is_array($me['data'] ?? null) ? $me['data'] : [];
        $id = $data['id'] ?? null;

        if (! is_scalar($id)) {
            throw new AccessRevoked('dotloop returned no account.');
        }

        return new ConnectedAccount((string) $id, is_string($data['email'] ?? null) ? $data['email'] : 'dotloop');
    }

    public function streams(): array
    {
        return ['deals' => DealStore::class];
    }

    /**
     * Each chosen profile's loops, a batch of up to 100 per page, changed
     * since the last sync; each loop with its details, participants,
     * documents and tasks.
     */
    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $state = is_string($cursor) ? (array) json_decode($cursor, true) : [];
        $profiles = is_array($state['profiles'] ?? null) ? array_values(array_filter($state['profiles'], is_string(...))) : $this->profiles($connection, $accessToken);
        $mark = is_string($state['mark'] ?? null) ? $state['mark'] : $since?->utc()->format('Y-m-d\TH:i:s\Z');
        $batch = is_int($state['batch'] ?? null) ? $state['batch'] : 1;
        $profile = $profiles[0] ?? null;
        $started = is_string($state['started'] ?? null) ? $state['started'] : CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s\Z');

        if ($profile === null) {
            return new SyncPage([], [], (string) json_encode(['mark' => $started]), true);
        }

        $query = ['batch_size' => 100, 'batch_number' => $batch, 'include_details' => 'true', 'sort' => 'updated:asc'];
        if ($mark !== null) {
            $query['filter'] = 'updated_min='.$mark;
        }
        $base = self::API.'/profile/'.rawurlencode($profile).'/loop';
        $loops = self::rows($this->json($this->http($accessToken)->get($base, $query)));
        $records = [];

        foreach ($loops as $loop) {
            $id = $loop['id'] ?? null;
            if (! is_scalar($id)) {
                continue;
            }
            $at = $base.'/'.rawurlencode((string) $id);
            $tasks = [];
            foreach (self::rows($this->json($this->http($accessToken)->get($at.'/tasklist/'))) as $list) {
                if (is_scalar($list['id'] ?? null)) {
                    $tasks = [...$tasks, ...self::rows($this->json($this->http($accessToken)->get($at.'/tasklist/'.rawurlencode((string) $list['id']).'/task')))];
                }
            }
            $records[] = DotloopRecords::deal(
                $loop,
                self::rows($this->json($this->http($accessToken)->get($at.'/participant'))),
                self::rows($this->json($this->http($accessToken)->get($at.'/folder', ['include_documents' => 'true']))),
                $tasks,
            );
        }

        if (count($loops) === 100) {
            return new SyncPage($records, [], (string) json_encode(['profiles' => $profiles, 'batch' => $batch + 1, 'mark' => $mark, 'started' => $started]), false);
        }

        $rest = array_slice($profiles, 1);

        return $rest === []
            ? new SyncPage($records, [], (string) json_encode(['mark' => $started]), true)
            : new SyncPage($records, [], (string) json_encode(['profiles' => $rest, 'batch' => 1, 'mark' => $mark, 'started' => $started]), false);
    }

    public function requestsPerMinute(): int
    {
        return 60;
    }

    /** A dotloop event, signed with the subscription's key, for the connection named in the address. */
    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        $connection = WebhookTarget::open($request->query('c'), self::PURPOSE);
        $signature = $request->header('X-DOTLOOP-WEBHOOK-SIGNATURE');
        $timestamp = $request->header('X-DOTLOOP-WEBHOOK-TIMESTAMP');
        $body = $request->getContent();

        if ($connection === null || ! is_string($signature) || ! is_string($timestamp) || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::WEBHOOK_SECONDS) {
            return null;
        }

        if (! hash_equals(hash_hmac('sha1', $timestamp.'.'.$body, self::signingKey($connection)), strtolower($signature))) {
            return null;
        }

        $payload = (array) json_decode($body, true);
        $event = $payload['eventId'] ?? $payload['id'] ?? null;

        return is_scalar($event) ? new WebhookDelivery('dotloop:'.$event, [$connection]) : null;
    }

    /** The chosen profiles: their loops are what's watched. */
    public function watchedResources(Connection $connection): array
    {
        return DotloopProfiles::chosen($connection);
    }

    public function subscribe(Connection $connection, string $accessToken, string $webhookUrl, string $resource): array
    {
        $made = $this->json($this->http($accessToken)->post(self::API.'/subscription', [
            'targetType' => 'PROFILE', 'targetId' => is_numeric($resource) ? (int) $resource : $resource, 'eventTypes' => self::EVENTS,
            'url' => $webhookUrl.'?c='.WebhookTarget::seal($connection->id, self::PURPOSE), 'signingKey' => self::signingKey($connection->id), 'externalId' => (string) $connection->id,
        ]));
        $data = is_array($made['data'] ?? null) ? $made['data'] : $made;
        $id = $data['id'] ?? null;

        return ['id' => is_scalar($id) ? (string) $id : '', 'resource_id' => $resource, 'expires_at' => null];
    }

    public function unsubscribe(Connection $connection, string $accessToken, string $channelId, ?string $resourceId): void
    {
        $this->http($accessToken)->delete(self::API.'/subscription/'.rawurlencode($channelId));
    }

    /** The folder REOS files signed copies in, made in the loop the first time. */
    public const SIGNED_FOLDER = 'Signed (REOS)';

    /**
     * The signed copy into the loop (B9.3): the loop is found among the
     * chosen profiles, then the file goes into REOS's folder there.
     */
    public function uploadDocument(Connection $connection, string $accessToken, Deal $deal, string $name, string $contents): bool
    {
        if ($deal->external_id === null) {
            return false;
        }

        foreach (DotloopProfiles::chosen($connection) as $profile) {
            $loop = self::API.'/profile/'.rawurlencode($profile).'/loop/'.rawurlencode($deal->external_id);
            $found = $this->http($accessToken)->get($loop);
            if ($found->status() === 404) {
                continue;
            }
            $this->json($found);

            $folder = null;
            foreach (self::rows($this->json($this->http($accessToken)->get($loop.'/folder'))) as $row) {
                if (($row['name'] ?? null) === self::SIGNED_FOLDER && is_scalar($row['id'] ?? null)) {
                    $folder = (string) $row['id'];
                }
            }
            if ($folder === null) {
                $made = $this->json($this->http($accessToken)->post($loop.'/folder', ['name' => self::SIGNED_FOLDER]));
                $id = (is_array($made['data'] ?? null) ? $made['data'] : $made)['id'] ?? null;
                $folder = is_scalar($id) ? (string) $id : throw new VendorUnavailable('dotloop made no folder.');
            }

            $this->json(Http::withToken($accessToken)->acceptJson()->timeout(120)->attach('file', $contents, $name, ['Content-Type' => 'application/pdf'])->post($loop.'/folder/'.rawurlencode($folder).'/document'));

            return true;
        }

        return false;
    }

    /** Each subscription's signing key: REOS's own, per connection, never stored. */
    public static function signingKey(int $connectionId): string
    {
        return hash_hmac('sha256', 'dotloop-signing:'.$connectionId, config()->string('app.key'));
    }

    /**
     * The profiles to read: the ones chosen, or every profile the account has
     * (remembered on the connection for the settings screen).
     *
     * @return list<string>
     */
    private function profiles(Connection $connection, string $accessToken): array
    {
        $list = [];
        foreach (self::rows($this->json($this->http($accessToken)->get(self::API.'/profile'))) as $profile) {
            if (is_scalar($profile['id'] ?? null)) {
                $list[] = ['id' => (string) $profile['id'], 'name' => is_string($profile['name'] ?? null) ? $profile['name'] : (string) $profile['id']];
            }
        }
        DotloopProfiles::remember($connection, $list);

        return DotloopProfiles::chosen($connection);
    }

    /** @param  array<array-key, mixed>  $body */
    private function tokens(array $body, ?string $refreshToken): TokenSet
    {
        $access = $body['access_token'] ?? null;

        if (! is_string($access) || $access === '') {
            throw new AccessRevoked('dotloop returned no token.');
        }

        $seconds = is_numeric($body['expires_in'] ?? null) ? (int) $body['expires_in'] : 43200;
        $scopes = is_string($body['scope'] ?? null) ? array_values(array_filter(explode(' ', $body['scope']))) : [];

        return new TokenSet($access, is_string($body['refresh_token'] ?? null) ? $body['refresh_token'] : $refreshToken, CarbonImmutable::now()->addSeconds($seconds), $scopes);
    }

    private function http(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(30);
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return list<array<array-key, mixed>>
     */
    private static function rows(array $body): array
    {
        $list = $body['data'] ?? (array_is_list($body) ? $body : []);
        $rows = [];
        foreach (is_array($list) ? $list : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<array-key, mixed> */
    private function json(Response|PromiseInterface $response): array
    {
        if (! $response instanceof Response) {
            throw new VendorUnavailable('dotloop did not answer.');
        }

        return match (true) {
            $response->status() === 401, $response->json('error') === 'invalid_grant' => throw new AccessRevoked('dotloop ended the session.'),
            $response->status() === 429 => throw new VendorRateLimited(60),
            $response->failed() => throw new VendorUnavailable('dotloop answered '.$response->status().'.'),
            default => (array) $response->json(),
        };
    }
}
