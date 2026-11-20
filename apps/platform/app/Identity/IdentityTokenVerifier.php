<?php

declare(strict_types=1);

namespace App\Identity;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks a Google or Apple ID token the phone app received: signed by the
 * provider (keys fetched from its published key set and cached for an hour),
 * issued for REOS, and not expired.
 *
 * The provider must be switched on in Settings › Integrations. On demo data
 * (outside production only) a token "simulator:<email>" signs in as that
 * email, so every journey persona can be tried without real accounts.
 */
final readonly class IdentityTokenVerifier
{
    public const SIMULATOR_PREFIX = 'simulator:';

    public function __construct(
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
        private Http $http,
        private Cache $cache,
    ) {}

    public function verify(IdentityProvider $provider, string $token): IdentityClaims
    {
        $definition = $this->catalogue->find($provider->integrationId());
        $mode = $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();

        if ($mode === IntegrationMode::Simulator && ! app()->isProduction()) {
            return $this->simulated($provider, $token);
        }

        $environment = $mode->environment();

        if ($environment === null) {
            throw SignInFailed::unavailable();
        }

        $keys = $this->vault->get($provider->integrationId(), $environment);
        $audiences = array_values(array_filter([
            $keys['client_id'] ?? null,
            $keys['ios_client_id'] ?? null,
            // Apple tokens from the phone app are issued to its bundle id.
            $provider === IdentityProvider::Apple ? config()->string('reos.mobile.bundle_id') : null,
        ]));

        // Allow a minute of clock difference between phones, Google, Apple and us.
        JWT::$leeway = 60;

        try {
            $payload = (array) JWT::decode($token, JWK::parseKeySet($this->keySet($provider)));
        } catch (Throwable $exception) {
            throw SignInFailed::invalidToken('Signature or expiry: '.$exception->getMessage());
        }

        if (! in_array($payload['iss'] ?? null, $provider->issuers(), true)) {
            throw SignInFailed::invalidToken('Wrong issuer');
        }

        if (! in_array($payload['aud'] ?? null, $audiences, true)) {
            throw SignInFailed::invalidToken('Token not issued for REOS');
        }

        $subject = $payload['sub'] ?? null;

        if (! is_string($subject) || $subject === '') {
            throw SignInFailed::invalidToken('No subject');
        }

        $email = is_string($payload['email'] ?? null) ? Str::lower($payload['email']) : null;
        $verified = in_array($payload['email_verified'] ?? false, [true, 'true'], true);

        return new IdentityClaims(
            provider: $provider,
            subject: $subject,
            email: $email,
            emailVerified: $email !== null && $verified,
            name: is_string($payload['name'] ?? null) ? $payload['name'] : null,
        );
    }

    /** @return array<mixed> */
    private function keySet(IdentityProvider $provider): array
    {
        $keys = $this->cache->remember("reos:identity:jwks:{$provider->value}", 3600, function () use ($provider): array {
            $response = $this->http->connectTimeout(3)->timeout(5)->get($provider->keysUrl());

            if (! $response->successful() || ! is_array($response->json('keys'))) {
                throw SignInFailed::invalidToken('Provider keys unavailable');
            }

            return (array) $response->json();
        });

        return $keys;
    }

    private function simulated(IdentityProvider $provider, string $token): IdentityClaims
    {
        if (! Str::startsWith($token, self::SIMULATOR_PREFIX)) {
            throw SignInFailed::invalidToken('Demo sign-in expects a simulator token');
        }

        $email = Str::lower(Str::after($token, self::SIMULATOR_PREFIX));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw SignInFailed::invalidToken('Demo token without an email');
        }

        return new IdentityClaims($provider, "simulated-{$email}", $email, true);
    }
}
