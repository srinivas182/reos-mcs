<?php

declare(strict_types=1);

namespace App\Connections;

use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OAuth 2 authorisation code with PKCE (RFC 6749, RFC 7636), shared by the
 * real drivers. Vendors differ only in their addresses and extra parameters.
 */
final readonly class OAuth2Client
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string, string>  $extraAuthorizeParameters
     */
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $authorizeUrl,
        private string $tokenUrl,
        private ?string $revokeUrl,
        private array $scopes,
        private array $extraAuthorizeParameters = [],
    ) {}

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return $this->authorizeUrl.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            ...$this->extraAuthorizeParameters,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        return $this->tokens([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
        ], null);
    }

    public function refresh(string $refreshToken): TokenSet
    {
        // Most vendors don't send the refresh token again; keep the one we have.
        return $this->tokens(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken], $refreshToken);
    }

    public function revoke(string $token): void
    {
        if ($this->revokeUrl === null) {
            return;
        }

        try {
            Http::asForm()->timeout(10)->post($this->revokeUrl, ['token' => $token]);
        } catch (ConnectionException) {
            // Best effort: the tokens are deleted on REOS's side either way.
        }
    }

    /** @param  array<string, string>  $parameters */
    private function tokens(array $parameters, ?string $keepRefreshToken): TokenSet
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->post($this->tokenUrl, [
                ...$parameters,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);
        } catch (ConnectionException $e) {
            throw new VendorUnavailable('Token endpoint unreachable.', previous: $e);
        }

        self::raiseFor($response);

        $access = $response->json('access_token');

        if (! is_string($access) || $access === '') {
            throw new VendorUnavailable('Token response without an access token.');
        }

        $refresh = $response->json('refresh_token');
        $expiresIn = $response->json('expires_in');
        $scope = $response->json('scope');

        return new TokenSet(
            $access,
            is_string($refresh) && $refresh !== '' ? $refresh : $keepRefreshToken,
            is_numeric($expiresIn) ? CarbonImmutable::now()->addSeconds((int) $expiresIn) : null,
            is_string($scope) ? array_values(array_filter(explode(' ', $scope))) : $this->scopes,
        );
    }

    /** Turns a vendor's answer into the engine's errors. */
    public static function raiseFor(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $error = $response->json('error');
        $error = is_string($error) ? $error : (is_array($error) && is_string($error['status'] ?? null) ? $error['status'] : '');

        if (in_array($error, ['invalid_grant', 'unauthorized_client', 'access_denied'], true) || $response->status() === 401) {
            throw new AccessRevoked("Refused by the vendor ({$response->status()} {$error}).");
        }

        $reason = $response->json('error.errors.0.reason');
        if ($response->status() === 429 || in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded'], true)) {
            $after = $response->header('Retry-After');

            throw new VendorRateLimited(is_numeric($after) ? max(1, (int) $after) : 60);
        }

        throw new VendorUnavailable("Vendor answered {$response->status()}.");
    }
}
