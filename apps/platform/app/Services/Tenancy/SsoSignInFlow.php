<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Organisation;
use App\Models\User;
use App\Sso\OidcClient;
use App\Sso\SamlClient;
use App\Sso\SsoClaims;
use App\Sso\SsoProblem;
use App\Sso\SsoProtocol;
use App\Sso\SsoStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The browser side of single sign-on (A4.2c).
 *
 * The identity provider knows one REOS address per tenant (its own domain,
 * else `<slug>.reos.com`), but members start on many: a brokerage's domain
 * shows its association's button. So:
 * 1. start on any address: if it isn't the tenant's own, hop there with a
 *    one-time key that remembers where the person started;
 * 2. on the tenant's address: keep state, nonce, PKCE verifier and SAML
 *    request ID in the session, then go to the provider;
 * 3. the answer comes back there; after every check, hand the person back
 *    to where they started with a one-time, 60-second ticket.
 * An admin's test sign-in runs the same way and records the result instead.
 */
final readonly class SsoSignInFlow
{
    private const SESSION = 'reos.sso';

    private const HOP = 'reos:sso:hop:';

    private const TICKET = 'reos:sso:ticket:';

    public function __construct(
        private SingleSignOn $sso,
        private OidcClient $oidc,
        private SamlClient $saml,
        private Brands $brands,
    ) {}

    /** An admin's test sign-in: the result shows on screen 57 at `$returnTo`. */
    public function test(Organisation $tenant, User $tester, string $returnTo): string
    {
        if ($this->sso->of($tenant) === null) {
            throw new SsoProblem('sso.errors.notSetUp');
        }

        return $this->hop($tenant, ['origin' => $this->sso->origin($tenant), 'test' => ['tester_id' => $tester->id, 'return' => $returnTo]]);
    }

    /** Step 1 and 2: the address to send the browser to next. */
    public function start(Organisation $tenant, Request $request): string
    {
        $canonical = $this->sso->origin($tenant);
        $here = $this->originOf($request);
        $hop = $request->filled('hop') ? Cache::pull(self::HOP.$request->string('hop')->toString()) : null;
        $hop = is_array($hop) && ($hop['tenant_id'] ?? null) === $tenant->id ? $hop : null;

        if ($hop === null && $here !== $canonical) {
            return $this->hop($tenant, ['origin' => $this->trusted($tenant, $request) ? $here : $canonical, 'test' => null]);
        }

        if ($hop !== null && $here !== $canonical) {
            return $this->hop($tenant, $hop);
        }

        $test = is_array($hop['test'] ?? null) ? $hop['test'] : null;
        $sso = $test === null ? $this->sso->live($tenant) : $this->sso->of($tenant);

        if ($sso === null) {
            throw new SsoProblem('sso.errors.notOn', status: 404);
        }

        $sp = $this->sso->serviceProvider($tenant);
        $state = Str::random(40);
        $saved = [
            'tenant_id' => $tenant->id,
            'state' => $state,
            'nonce' => Str::random(40),
            'verifier' => Str::random(64),
            'request_id' => null,
            'origin' => is_string($hop['origin'] ?? null) ? $hop['origin'] : $canonical,
            'test' => $test,
        ];

        if ($sso->protocol === SsoProtocol::Oidc) {
            $url = $this->oidc->authorizeUrl($sso, $sp->redirectUri(), $state, $saved['nonce'], $saved['verifier']);
        } else {
            ['url' => $url, 'request_id' => $saved['request_id']] = $this->saml->loginUrl($sso, $sp, $state);
        }

        $request->session()->put(self::SESSION, $saved);

        return $url;
    }

    /**
     * Step 3: checks the answer. Returns who signed in and where to hand
     * them, or, for a test, where to show the result.
     *
     * @return array{test: bool, user: User|null, created: bool, next: string|null}
     */
    public function finish(Organisation $tenant, Request $request): array
    {
        /** @var array{tenant_id?: int, state?: string, nonce?: string, verifier?: string, request_id?: string|null, origin?: string, test?: array{tester_id: int, return: string}|null} $saved */
        $saved = (array) $request->session()->pull(self::SESSION, []);
        $state = $request->input('RelayState', $request->input('state'));

        if (($saved['tenant_id'] ?? null) !== $tenant->id || ! isset($saved['state'], $saved['nonce'], $saved['verifier']) || ! is_string($state) || ! hash_equals($saved['state'], $state)) {
            throw new SsoProblem('sso.errors.expired', 'State mismatch (expired or replayed sign-in)');
        }

        $test = $saved['test'] ?? null;

        try {
            $claims = $this->claims($tenant, $request, $saved);
        } catch (SsoProblem $e) {
            if ($test === null) {
                throw $e;
            }

            $this->sso->recordTest($tenant, false, __($e->reason).' ('.$e->getMessage().')', User::query()->find($test['tester_id']));

            return ['test' => true, 'user' => null, 'created' => false, 'next' => $test['return']];
        }

        if ($test !== null) {
            $detail = trim(($claims->email ?? __('sso.test.noEmail')).($claims->name !== null ? " ({$claims->name})" : ''));
            $this->sso->recordTest($tenant, true, $detail, User::query()->find($test['tester_id']));

            return ['test' => true, 'user' => null, 'created' => false, 'next' => $test['return']];
        }

        ['user' => $user, 'created' => $created] = $this->sso->signIn($tenant, $claims);
        $origin = $saved['origin'] ?? $this->sso->origin($tenant);

        if ($origin === $this->originOf($request)) {
            return ['test' => false, 'user' => $user, 'created' => $created, 'next' => null];
        }

        $ticket = Str::random(48);
        Cache::put(self::TICKET.$ticket, ['user_id' => $user->id, 'origin' => $origin], 60);

        return ['test' => false, 'user' => $user, 'created' => $created, 'next' => "{$origin}/sso/complete/{$ticket}"];
    }

    /** Back where they started: the one-time ticket, on the address it was made for. */
    public function redeem(string $ticket, Request $request): User
    {
        $saved = Cache::pull(self::TICKET.$ticket);

        $userId = is_array($saved) ? ($saved['user_id'] ?? null) : null;

        if (! is_int($userId) || ($saved['origin'] ?? null) !== $this->originOf($request)) {
            throw new SsoProblem('sso.errors.expired', 'Ticket unknown, used or for another address');
        }

        return User::query()->whereKey($userId)->firstOrFail();
    }

    /** @param  array{request_id?: string|null, nonce?: string, verifier?: string, test?: array{tester_id: int, return: string}|null}  $saved */
    private function claims(Organisation $tenant, Request $request, array $saved): SsoClaims
    {
        $sso = $this->sso->of($tenant) ?? throw new SsoProblem('sso.errors.notSetUp');
        $sp = $this->sso->serviceProvider($tenant);

        if ($sso->status === SsoStatus::Draft && ($saved['test'] ?? null) === null) {
            throw new SsoProblem('sso.errors.notOn');
        }

        if ($sso->protocol === SsoProtocol::Oidc) {
            if ($request->filled('error') || ! $request->filled('code')) {
                throw new SsoProblem('sso.errors.cancelled', 'Provider returned an error');
            }

            return $this->oidc->finish($sso, $sp->redirectUri(), $request->string('code')->toString(), (string) ($saved['verifier'] ?? ''), (string) ($saved['nonce'] ?? ''));
        }

        if (! $request->filled('SAMLResponse') || ! is_string($saved['request_id'] ?? null)) {
            throw new SsoProblem('sso.errors.cancelled', 'No SAML response');
        }

        return $this->saml->finish($sso, $sp, $request->string('SAMLResponse')->toString(), $saved['request_id']);
    }

    /** @param  array<string, mixed>  $data */
    private function hop(Organisation $tenant, array $data): string
    {
        $key = Str::random(40);
        Cache::put(self::HOP.$key, ['tenant_id' => $tenant->id, ...$data], 300);

        return $this->sso->origin($tenant).'/sso/'.$tenant->slug.'/start?hop='.$key;
    }

    /** The address a request came to, as `scheme://host[:port]`. */
    private function originOf(Request $request): string
    {
        return $request->getSchemeAndHttpHost();
    }

    /** Started on this tenant's address or one of its brokerages', schools' or teams'. */
    private function trusted(Organisation $tenant, Request $request): bool
    {
        $host = $this->brands->tenantForHost($request->getHost());

        for ($current = $host, $depth = 0; $current !== null && $depth < 5; $current = $current->parent, $depth++) {
            if ($current->id === $tenant->id) {
                return true;
            }
        }

        return false;
    }
}
