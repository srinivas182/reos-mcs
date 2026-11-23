<?php

declare(strict_types=1);

namespace App\Connections\Drivers\DocuSign;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Drivers\FollowUpBoss\WebhookTarget;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\SendsForSignature;
use App\Connections\Stores\EnvelopeStore;
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
 * DocuSign eSignature (B9.3, ADR 0038), the agent's own account: their
 * signing, branding and billing stay theirs.
 *
 * - Sign-in: OAuth 2 authorization code with PKCE (`signature` scope);
 *   tokens last about eight hours and refresh. The account and its API
 *   base URI come from `userinfo` and are kept on the connection.
 * - Send: REOS creates a draft envelope (documents and signers, no
 *   fields) and opens DocuSign's sender view; the agent places the fields
 *   and presses Send there.
 * - Follow: each envelope asks DocuSign Connect to report its events to
 *   REOS (HMAC-SHA256 when the account's key is set in Settings ›
 *   Integrations); a delivery only queues a sync, which reads envelopes
 *   changed since the last one. The 15-minute poll is the backstop.
 */
final readonly class DocuSignDriver implements ConnectorDriver, SendsForSignature
{
    public const INTEGRATION = 'docusign';

    public const STREAMS = ['envelopes' => 'read'];

    /** @var array{can: list<string>, never: list<string>} */
    public const CONSENT = ['can' => ['prepare_envelopes', 'read_envelopes', 'file_signed'], 'never' => ['send_without_you']];

    public const PURPOSE = 'docusign-webhook';

    public const DEMO_AUTH = 'account-d.docusign.com';

    public const LIVE_AUTH = 'account.docusign.com';

    private const ENVELOPE_EVENTS = ['sent', 'delivered', 'completed', 'declined', 'voided'];

    private const RECIPIENT_EVENTS = ['Sent', 'Delivered', 'Completed', 'Declined'];

    public function __construct(private string $integrationKey, private string $secretKey, private string $authServer = self::DEMO_AUTH, private string $hmacKey = '') {}

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return 'https://'.$this->authServer.'/oauth/auth?'.http_build_query([
            'response_type' => 'code', 'scope' => 'signature', 'client_id' => $this->integrationKey, 'redirect_uri' => $redirectUri, 'state' => $state,
            'code_challenge' => $codeChallenge, 'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        return $this->tokens($this->json($this->auth()->post('https://'.$this->authServer.'/oauth/token', ['grant_type' => 'authorization_code', 'code' => $code, 'code_verifier' => $codeVerifier, 'redirect_uri' => $redirectUri])), null);
    }

    public function refresh(string $refreshToken): TokenSet
    {
        return $this->tokens($this->json($this->auth()->post('https://'.$this->authServer.'/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken])), $refreshToken);
    }

    /** DocuSign has no revoke endpoint for authorization-code grants: the tokens lapse, and the agent can remove REOS under Apps and Keys. */
    public function revoke(string $token): void {}

    public function account(TokenSet $tokens): ConnectedAccount
    {
        $me = $this->userinfo($tokens->accessToken);
        $sub = $me['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            throw new AccessRevoked('DocuSign returned no user.');
        }

        return new ConnectedAccount($sub, is_string($me['email'] ?? null) ? $me['email'] : 'DocuSign');
    }

    public function streams(): array
    {
        return ['envelopes' => EnvelopeStore::class];
    }

    /** Envelopes whose status changed since the last pass (30 days on the first), with their signers. */
    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $state = is_string($cursor) ? (array) json_decode($cursor, true) : [];
        $from = is_string($state['from'] ?? null) ? $state['from'] : ($since ?? CarbonImmutable::now()->subDays(30))->utc()->format('Y-m-d\TH:i:s\Z');
        $start = is_int($state['start'] ?? null) ? $state['start'] : 0;
        $started = is_string($state['started'] ?? null) ? $state['started'] : CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s\Z');

        $body = $this->json($this->http($accessToken)->get($this->base($connection, $accessToken).'/envelopes', ['from_date' => $from, 'include' => 'recipients', 'count' => 100, 'start_position' => $start]));
        $records = [];
        foreach (is_array($body['envelopes'] ?? null) ? $body['envelopes'] : [] as $envelope) {
            if (is_array($envelope)) {
                $records[] = EnvelopeRecords::envelope($envelope);
            }
        }

        $end = $body['endPosition'] ?? null;
        $total = $body['totalSetSize'] ?? null;
        $more = is_numeric($end) && is_numeric($total) && (int) $end + 1 < (int) $total;

        return $more
            ? new SyncPage($records, [], (string) json_encode(['from' => $from, 'start' => (int) $end + 1, 'started' => $started]), false)
            : new SyncPage($records, [], (string) json_encode(['from' => $started]), true);
    }

    public function requestsPerMinute(): int
    {
        // 3,000 an hour per account.
        return 45;
    }

    /** A DocuSign Connect delivery for the connection named in the address; it only queues a sync, so its body is never trusted. */
    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        $connection = WebhookTarget::open($request->query('c'), self::PURPOSE);
        $body = $request->getContent();

        if ($connection === null) {
            return null;
        }

        if ($this->hmacKey !== '') {
            $expected = base64_encode(hash_hmac('sha256', $body, $this->hmacKey, true));
            $given = array_filter([$request->header('X-DocuSign-Signature-1'), $request->header('X-DocuSign-Signature-2')], is_string(...));
            if (! array_any($given, static fn (string $signature): bool => hash_equals($expected, $signature))) {
                return null;
            }
        }

        $payload = (array) json_decode($body, true);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $envelope = $data['envelopeId'] ?? null;
        $event = $payload['event'] ?? null;
        $at = $payload['generatedDateTime'] ?? null;

        return is_string($envelope) && is_string($event) ? new WebhookDelivery('docusign:'.$envelope.':'.$event.':'.(is_string($at) ? $at : ''), [$connection]) : null;
    }

    public function draft(Connection $connection, string $accessToken, string $subject, array $documents, array $signers, ?string $webhookUrl): string
    {
        $body = [
            'emailSubject' => $subject,
            'status' => 'created',
            'documents' => array_map(static fn (array $doc, int $i): array => ['documentBase64' => base64_encode($doc['contents']), 'name' => $doc['name'], 'fileExtension' => 'pdf', 'documentId' => (string) ($i + 1)], $documents, array_keys($documents)),
            'recipients' => ['signers' => array_map(static fn (array $s, int $i): array => ['email' => $s['email'], 'name' => $s['name'], 'recipientId' => (string) ($i + 1), 'routingOrder' => (string) $s['routing_order']], $signers, array_keys($signers))],
        ];

        if ($webhookUrl !== null) {
            $body['eventNotification'] = [
                'url' => $webhookUrl.'?c='.WebhookTarget::seal($connection->id, self::PURPOSE), 'requireAcknowledgment' => 'true', 'loggingEnabled' => 'true', 'includeHMAC' => $this->hmacKey !== '' ? 'true' : 'false',
                'eventData' => ['version' => 'restv2.1', 'format' => 'json'],
                'envelopeEvents' => array_map(static fn (string $e): array => ['envelopeEventStatusCode' => $e], self::ENVELOPE_EVENTS),
                'recipientEvents' => array_map(static fn (string $e): array => ['recipientEventStatusCode' => $e], self::RECIPIENT_EVENTS),
            ];
        }

        $made = $this->json($this->http($accessToken)->post($this->base($connection, $accessToken).'/envelopes', $body));
        $id = $made['envelopeId'] ?? null;

        return is_string($id) && $id !== '' ? $id : throw new VendorUnavailable('DocuSign made no envelope.');
    }

    public function senderView(Connection $connection, string $accessToken, string $envelopeId, string $returnUrl): string
    {
        $view = $this->json($this->http($accessToken)->post($this->base($connection, $accessToken).'/envelopes/'.rawurlencode($envelopeId).'/views/sender', [
            'returnUrl' => $returnUrl, 'settings' => ['startingScreen' => 'Tagger', 'sendButtonAction' => 'send', 'showBackButton' => 'false'],
        ]));
        $url = $view['url'] ?? null;

        return is_string($url) && str_starts_with($url, 'https://') ? $url : throw new VendorUnavailable('DocuSign gave no sender view.');
    }

    public function envelope(Connection $connection, string $accessToken, string $envelopeId): array
    {
        $base = $this->base($connection, $accessToken).'/envelopes/'.rawurlencode($envelopeId);
        $envelope = $this->json($this->http($accessToken)->get($base));
        $recipients = $this->json($this->http($accessToken)->get($base.'/recipients'));
        $record = EnvelopeRecords::envelope([...$envelope, 'recipients' => $recipients]);

        return ['status' => $record['status'], 'signers' => $record['signers']];
    }

    public function signedCopy(Connection $connection, string $accessToken, string $envelopeId): array
    {
        $base = $this->base($connection, $accessToken).'/envelopes/'.rawurlencode($envelopeId).'/documents/';

        return ['signed' => $this->pdf($this->http($accessToken)->accept('application/pdf')->get($base.'combined')), 'certificate' => $this->pdf($this->http($accessToken)->accept('application/pdf')->get($base.'certificate'))];
    }

    /** The account's eSignature API base: from `userinfo` once, then kept on the connection. */
    private function base(Connection $connection, string $accessToken): string
    {
        $site = is_array($connection->meta['docusign'] ?? null) ? $connection->meta['docusign'] : [];
        $id = $site['account_id'] ?? null;
        $uri = $site['base_uri'] ?? null;

        if (! is_string($id) || ! is_string($uri)) {
            $accounts = $this->userinfo($accessToken)['accounts'] ?? [];
            $chosen = [];
            foreach (is_array($accounts) ? $accounts : [] as $account) {
                if (is_array($account) && ($chosen === [] || ($account['is_default'] ?? false) === true)) {
                    $chosen = $account;
                }
            }
            $id = $chosen['account_id'] ?? null;
            $uri = $chosen['base_uri'] ?? null;

            if (! is_string($id) || ! is_string($uri) || ! str_starts_with($uri, 'https://')) {
                throw new AccessRevoked('No DocuSign account to send from.');
            }

            $uri = rtrim($uri, '/');
            if ($connection->exists) {
                $connection->forceFill(['meta' => [...$connection->meta, 'docusign' => ['account_id' => $id, 'base_uri' => $uri]]])->save();
            }
        }

        return $uri.'/restapi/v2.1/accounts/'.rawurlencode($id);
    }

    /** @return array<array-key, mixed> */
    private function userinfo(string $accessToken): array
    {
        return $this->json($this->http($accessToken)->get('https://'.$this->authServer.'/oauth/userinfo'));
    }

    private function auth(): PendingRequest
    {
        return Http::asForm()->withBasicAuth($this->integrationKey, $this->secretKey)->acceptJson()->timeout(30);
    }

    private function http(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)->acceptJson()->timeout(60);
    }

    /** @param  array<array-key, mixed>  $body */
    private function tokens(array $body, ?string $refreshToken): TokenSet
    {
        $access = $body['access_token'] ?? null;

        if (! is_string($access) || $access === '') {
            throw new AccessRevoked('DocuSign returned no token.');
        }

        $seconds = is_numeric($body['expires_in'] ?? null) ? (int) $body['expires_in'] : 28800;
        $refresh = is_string($body['refresh_token'] ?? null) ? $body['refresh_token'] : $refreshToken;

        return new TokenSet($access, $refresh, CarbonImmutable::now()->addSeconds($seconds), ['signature']);
    }

    private function pdf(Response|PromiseInterface $response): string
    {
        if (! $response instanceof Response) {
            throw new VendorUnavailable('DocuSign did not answer.');
        }
        $this->json($response, decode: false);

        return $response->body();
    }

    /** @return array<array-key, mixed> */
    private function json(Response|PromiseInterface $response, bool $decode = true): array
    {
        if (! $response instanceof Response) {
            throw new VendorUnavailable('DocuSign did not answer.');
        }

        return match (true) {
            $response->status() === 401 => throw new AccessRevoked('DocuSign refused the token.'),
            $response->status() === 429 => throw new VendorRateLimited(60),
            $response->failed() => throw new VendorUnavailable('DocuSign answered '.$response->status().'.'),
            default => $decode ? (array) $response->json() : [],
        };
    }
}
