<?php

declare(strict_types=1);

namespace App\Sso;

use App\Models\TenantSso;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * OpenID Connect single sign-on with any standards-based provider (Okta,
 * Microsoft Entra ID, Google Workspace, Auth0, Keycloak): discovery, the
 * authorisation-code flow with state, nonce and PKCE, and an ID token checked
 * against the provider's published keys.
 */
final readonly class OidcClient
{
    public function __construct(private Http $http, private TestIdentityProvider $testIdp) {}

    /**
     * Reads the provider's discovery document.
     *
     * @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string}
     */
    public function discover(string $issuer): array
    {
        $issuer = rtrim($issuer, '/');
        $document = $this->testIdp->handles($issuer)
            ? $this->testIdp->discovery()
            : $this->getJson($issuer.'/.well-known/openid-configuration');

        $found = [];

        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
            $value = $document[$key] ?? null;

            if (! is_string($value) || $value === '' || ($key !== 'issuer' && ! $this->testIdp->handles($issuer) && ! ProviderAddress::allowed($value))) {
                throw new SsoProblem('sso.errors.discovery', "Discovery document has no usable {$key}");
            }

            $found[$key] = $value;
        }

        if (rtrim($found['issuer'], '/') !== $issuer) {
            throw new SsoProblem('sso.errors.discovery', 'Discovery issuer does not match');
        }

        return $found;
    }

    /** Where to send the browser to sign in. */
    public function authorizeUrl(TenantSso $sso, string $redirectUri, string $state, string $nonce, string $verifier): string
    {
        $endpoint = $sso->setting('authorization_endpoint');

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query([
            'client_id' => $sso->setting('client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    /** Exchanges the code and checks the ID token: signature, issuer, audience, expiry and nonce. */
    public function finish(TenantSso $sso, string $redirectUri, string $code, string $verifier, string $nonce): SsoClaims
    {
        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $sso->setting('client_id'),
            'client_secret' => (string) $sso->client_secret,
            'code_verifier' => $verifier,
        ];

        if ($this->testIdp->handles($sso->setting('issuer'))) {
            $answer = $this->testIdp->token($form);
        } else {
            try {
                $response = $this->http->asForm()->acceptJson()->connectTimeout(5)->timeout(10)->post($sso->setting('token_endpoint'), $form);
            } catch (ConnectionException) {
                throw new SsoProblem('sso.errors.unreachable', 'Token endpoint unreachable', status: 503);
            }

            $answer = $response->successful() ? (array) $response->json() : [];
        }

        $token = $answer['id_token'] ?? null;

        if (! is_string($token)) {
            throw new SsoProblem('sso.errors.rejected', 'Code exchange failed');
        }

        return $this->verify($sso, $token, $nonce);
    }

    public function verify(TenantSso $sso, string $token, string $nonce): SsoClaims
    {
        try {
            JWT::$leeway = 60;
            $claims = (array) JWT::decode($token, JWK::parseKeySet($this->keys($sso), 'RS256'));
        } catch (Throwable $e) {
            throw new SsoProblem('sso.errors.rejected', 'ID token rejected: '.$e->getMessage());
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];

        $checks = [
            'issuer' => is_string($claims['iss'] ?? null) && rtrim($claims['iss'], '/') === rtrim($sso->setting('issuer'), '/'),
            'audience' => in_array($sso->setting('client_id'), $audiences, true),
            'nonce' => is_string($claims['nonce'] ?? null) && hash_equals($nonce, $claims['nonce']),
            'subject' => is_string($claims['sub'] ?? null) && $claims['sub'] !== '',
        ];

        foreach ($checks as $check => $passed) {
            if (! $passed) {
                throw new SsoProblem('sso.errors.rejected', "ID token {$check} check failed");
            }
        }

        $email = is_string($claims['email'] ?? null) ? mb_strtolower($claims['email']) : null;

        // A provider that says the email isn't verified doesn't get it used.
        if (($claims['email_verified'] ?? null) === false) {
            $email = null;
        }

        return new SsoClaims((string) $claims['sub'], $email, is_string($claims['name'] ?? null) ? $claims['name'] : null);
    }

    /**
     * The provider's signing keys, cached for an hour.
     *
     * @return array<string, mixed>
     */
    private function keys(TenantSso $sso): array
    {
        if ($this->testIdp->handles($sso->setting('issuer'))) {
            return $this->testIdp->jwks();
        }

        $uri = $sso->setting('jwks_uri');

        /** @var array<string, mixed> */
        return Cache::remember('reos:sso:jwks:'.sha1($uri), 3600, fn (): array => $this->getJson($uri));
    }

    /** @return array<string, mixed> */
    private function getJson(string $url): array
    {
        if (! ProviderAddress::allowed($url)) {
            throw new SsoProblem('sso.errors.address', "Address not allowed: {$url}");
        }

        try {
            $response = $this->http->acceptJson()->connectTimeout(5)->timeout(10)->withOptions(['allow_redirects' => false])->get($url);
        } catch (ConnectionException) {
            throw new SsoProblem('sso.errors.unreachable', "Unreachable: {$url}", status: 503);
        }

        $json = $response->successful() ? $response->json() : null;

        if (! is_array($json)) {
            throw new SsoProblem('sso.errors.discovery', "No JSON at {$url}");
        }

        /** @var array<string, mixed> $json */
        return $json;
    }
}
