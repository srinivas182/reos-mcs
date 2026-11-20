<?php

declare(strict_types=1);

namespace App\Identity;

use App\Integrations\Catalogue;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Google and Apple sign-in on the web: the OpenID Connect authorisation-code
 * flow with state, nonce and (for Google) PKCE, ending in the same ID-token
 * check the phone app uses.
 *
 * The redirect address is the one Settings › Integrations shows for each
 * provider: /integrations/{google-sign-in|apple-sign-in}/callback.
 * On demo data (outside production only) the flow goes to a page listing the
 * journey personas instead of the provider.
 */
final readonly class WebOAuth
{
    private const SESSION = 'reos.oauth';

    public function __construct(
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
        private IdentityTokenVerifier $verifier,
        private Http $http,
    ) {}

    /** Where to send the browser to start signing in. */
    public function start(IdentityProvider $provider, Request $request): string
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put(self::SESSION.".{$provider->value}", compact('state', 'nonce', 'verifier'));

        $environment = $this->environment($provider);

        if ($environment === null) {
            return route('auth.social.demo', ['provider' => $provider->value, 'state' => $state]);
        }

        $keys = $this->vault->get($provider->integrationId(), $environment);

        return match ($provider) {
            IdentityProvider::Google => 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
                'client_id' => $keys['client_id'] ?? '',
                'redirect_uri' => $this->redirectUri($provider),
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'nonce' => $nonce,
                'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
                'code_challenge_method' => 'S256',
                'prompt' => 'select_account',
            ]),
            // Apple posts the answer back (form_post) when name or email is asked for.
            IdentityProvider::Apple => 'https://appleid.apple.com/auth/authorize?'.http_build_query([
                'client_id' => $keys['client_id'] ?? '',
                'redirect_uri' => $this->redirectUri($provider),
                'response_type' => 'code',
                'response_mode' => 'form_post',
                'scope' => 'name email',
                'state' => $state,
                'nonce' => $nonce,
            ]),
        };
    }

    /**
     * Checks the provider's answer and returns who signed in, plus the name
     * Apple sends on a person's first sign-in only.
     *
     * @return array{claims: IdentityClaims, name: string|null}
     */
    public function finish(IdentityProvider $provider, Request $request): array
    {
        /** @var array{state?: string, nonce?: string, verifier?: string} $saved */
        $saved = (array) $request->session()->pull(self::SESSION.".{$provider->value}", []);
        $state = $request->input('state');

        if (! isset($saved['state'], $saved['nonce'], $saved['verifier']) || ! is_string($state) || ! hash_equals($saved['state'], $state)) {
            throw SignInFailed::invalidToken('State mismatch (expired or replayed sign-in)');
        }

        if ($request->filled('error')) {
            throw new SignInFailed('identity.errors.cancelled', 'Provider returned an error');
        }

        $environment = $this->environment($provider);

        if ($environment === null) {
            // Demo personas (never in production: environment() is null only on demo data outside it).
            $email = $request->string('demo_email')->toString();

            return ['claims' => $this->verifier->verify($provider, IdentityTokenVerifier::SIMULATOR_PREFIX.$email), 'name' => null];
        }

        $keys = $this->vault->get($provider->integrationId(), $environment);
        $code = $request->string('code')->toString();

        try {
            $response = $this->http->asForm()->acceptJson()->connectTimeout(5)->timeout(10)->post(
                $provider === IdentityProvider::Google ? 'https://oauth2.googleapis.com/token' : 'https://appleid.apple.com/auth/token',
                array_filter([
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri($provider),
                    'client_id' => $keys['client_id'] ?? '',
                    'client_secret' => $provider === IdentityProvider::Google ? ($keys['client_secret'] ?? '') : $this->appleClientSecret($keys),
                    'code_verifier' => $provider === IdentityProvider::Google ? $saved['verifier'] : null,
                ]),
            );
        } catch (ConnectionException) {
            throw new SignInFailed('identity.errors.providerUnreachable', 'Token endpoint unreachable', status: 503);
        }

        $idToken = $response->json('id_token');

        if (! $response->successful() || ! is_string($idToken)) {
            throw SignInFailed::invalidToken("Code exchange failed ({$response->status()})");
        }

        return [
            'claims' => $this->verifier->verify($provider, $idToken, $saved['nonce']),
            'name' => $this->appleName($request),
        ];
    }

    public function redirectUri(IdentityProvider $provider): string
    {
        return url("integrations/{$provider->integrationId()}/callback");
    }

    /** Null when the provider runs on demo data (outside production); throws when it's off. */
    private function environment(IdentityProvider $provider): ?CredentialEnvironment
    {
        $definition = $this->catalogue->find($provider->integrationId());
        $mode = $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();

        if ($mode === IntegrationMode::Simulator && ! app()->isProduction()) {
            return null;
        }

        return $mode->environment() ?? throw SignInFailed::unavailable();
    }

    /**
     * Apple wants a short-lived client secret: a JWT signed with the
     * Sign in with Apple key (ES256).
     *
     * @param  array<string, string>  $keys
     */
    private function appleClientSecret(array $keys): string
    {
        return JWT::encode([
            'iss' => $keys['team_id'] ?? '',
            'iat' => time(),
            'exp' => time() + 300,
            'aud' => 'https://appleid.apple.com',
            'sub' => $keys['client_id'] ?? '',
        ], $keys['private_key'] ?? '', 'ES256', $keys['key_id'] ?? null);
    }

    private function appleName(Request $request): ?string
    {
        $user = json_decode($request->string('user')->toString(), true);
        $name = is_array($user) && is_array($user['name'] ?? null) ? $user['name'] : [];
        $full = trim(implode(' ', array_filter([
            is_string($name['firstName'] ?? null) ? $name['firstName'] : null,
            is_string($name['lastName'] ?? null) ? $name['lastName'] : null,
        ])));

        return $full === '' ? null : $full;
    }
}
