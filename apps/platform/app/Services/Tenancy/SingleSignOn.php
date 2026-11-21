<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Authorization\Role;
use App\Enums\Locale;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\SsoIdentity;
use App\Models\TenantSso;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Sso\OidcClient;
use App\Sso\ProviderAddress;
use App\Sso\SamlClient;
use App\Sso\ServiceProvider;
use App\Sso\SsoClaims;
use App\Sso\SsoProblem;
use App\Sso\SsoProtocol;
use App\Sso\SsoStatus;
use App\Sso\TestIdentityProvider;
use App\Tenancy\Tenancy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A tenant's single sign-on (A4.2c, screen 57; ADR 0016).
 *
 * - Settings: SAML metadata (URL, upload or the test identity provider) or
 *   an OIDC issuer, client ID and secret. Saving returns to Draft.
 * - A test sign-in by an admin must pass before it can be turned on.
 * - Sign-in links a provider account to a REOS account (see {@see signIn()}).
 * Every change is audited with the tenant.
 */
final readonly class SingleSignOn
{
    public const AUDIT_SUBJECT = 'sso';

    public function __construct(
        private Tenancy $tenancy,
        private AuditLogger $audit,
        private OidcClient $oidc,
        private SamlClient $saml,
        private TestIdentityProvider $testIdp,
        private TenantDomains $domains,
        private Memberships $memberships,
        private Http $http,
        private Brands $brands,
    ) {}

    public function of(Organisation $tenant): ?TenantSso
    {
        return $this->tenancy->asPlatform(fn (): ?TenantSso => TenantSso::query()->where('organisation_id', $tenant->id)->first());
    }

    /** Switched on for members: the button shows and sign-in works. */
    public function live(Organisation $tenant): ?TenantSso
    {
        $sso = $this->of($tenant);

        return $sso?->status === SsoStatus::On ? $sso : null;
    }

    /**
     * The tenant's own SSO, or the nearest parent's that is on, for the
     * sign-in page on a tenant's address: a Lone Star Realty agent signs in
     * with Texas REALTORS® (journey screen 2).
     */
    public function forSignIn(Organisation $tenant): ?Organisation
    {
        for ($current = $tenant, $depth = 0; $current !== null && $depth < 5; $current = $current->parent, $depth++) {
            if ($current->type !== OrganisationType::Platform && $this->live($current) !== null) {
                return $current;
            }
        }

        return null;
    }

    /**
     * The single sign-on button on the sign-in page of this address, if the
     * tenant here (or its association) has single sign-on on.
     *
     * @return array{name: string, url: string}|null
     */
    public function button(Request $request): ?array
    {
        $here = $this->brands->tenantForHost($request->getHost());
        $tenant = $here === null ? null : $this->forSignIn($here);

        return $tenant === null ? null : [
            'name' => $tenant->name,
            'url' => $request->getSchemeAndHttpHost().'/sso/'.$tenant->slug.'/start',
        ];
    }

    /**
     * Screen 57's single sign-on card.
     *
     * @return array<string, mixed>
     */
    public function screen(Organisation $tenant): array
    {
        $sso = $this->of($tenant);
        $sp = $this->serviceProvider($tenant);

        return [
            'settings' => $sso === null ? null : [
                ...$this->describe($sso),
                'has_secret' => $sso->client_secret !== null && $sso->client_secret !== '',
                'certificate_expires' => $this->certificateExpiry($sso),
                'test_provider' => $this->testIdp->handles($sso->setting('issuer')) || $sso->setting('entity_id') === TestIdentityProvider::SAML_ENTITY_ID,
                'last_test' => $sso->last_test_at === null ? null : [
                    'at' => $sso->last_test_at->toIso8601String(),
                    'passed' => (bool) $sso->last_test_passed,
                    'detail' => $sso->last_test_detail,
                ],
                'enabled_at' => $sso->enabled_at?->toIso8601String(),
            ],
            'service_provider' => [
                'entity_id' => $sp->entityId,
                'acs_url' => $sp->acsUrl,
                'metadata_url' => $sp->metadataUrl,
                'redirect_uri' => $sp->redirectUri(),
            ],
            'members' => $this->tenancy->asPlatform(fn (): int => SsoIdentity::query()->where('organisation_id', $tenant->id)->count()),
            'test_provider_available' => $this->testIdp->available(),
        ];
    }

    /** The soonest expiry of the provider's signing certificates (SAML). */
    private function certificateExpiry(TenantSso $sso): ?string
    {
        $soonest = null;

        foreach ($sso->certificates() as $certificate) {
            $parsed = @openssl_x509_parse("-----BEGIN CERTIFICATE-----\n".chunk_split($certificate, 64, "\n")."-----END CERTIFICATE-----\n");
            $until = is_array($parsed) && is_int($parsed['validTo_time_t'] ?? null) ? $parsed['validTo_time_t'] : null;
            $soonest = $until === null ? $soonest : min($soonest ?? $until, $until);
        }

        return $soonest === null ? null : date(DATE_ATOM, $soonest);
    }

    /** REOS's addresses for this tenant, on its own address. */
    public function serviceProvider(Organisation $tenant): ServiceProvider
    {
        return ServiceProvider::at($this->origin($tenant), (string) $tenant->slug);
    }

    /** `scheme://host[:port]` of the tenant's address (live custom domain, else `<slug>.<base domain>`). */
    public function origin(Organisation $tenant): string
    {
        $app = parse_url(config()->string('app.url'));
        $scheme = is_array($app) && isset($app['scheme']) ? $app['scheme'] : 'https';
        $port = is_array($app) && isset($app['port']) ? ':'.$app['port'] : '';
        $domain = $this->domains->of($tenant);
        $host = $domain !== null && $domain->live() ? $domain->hostname : $tenant->slug.'.'.config()->string('reos.tenancy.base_domain');

        return "{$scheme}://{$host}{$port}";
    }

    /**
     * @param  array{protocol: string, metadata_url?: string|null, metadata_xml?: string|null, issuer?: string|null, client_id?: string|null, client_secret?: string|null}  $input
     */
    public function save(Organisation $tenant, array $input, User $actor): TenantSso
    {
        $protocol = SsoProtocol::from($input['protocol']);
        $existing = $this->of($tenant);
        $secret = null;

        if ($protocol === SsoProtocol::Oidc) {
            $issuer = rtrim(trim((string) ($input['issuer'] ?? '')), '/');
            $clientId = trim((string) ($input['client_id'] ?? ''));
            $newSecret = (string) ($input['client_secret'] ?? '');
            $secret = $newSecret !== '' ? $newSecret : ($existing?->protocol === SsoProtocol::Oidc ? $existing->client_secret : null);

            if ($secret === null || $secret === '') {
                throw ValidationException::withMessages(['client_secret' => __('sso.errors.secret')]);
            }

            if (! $this->testIdp->handles($issuer) && ! ProviderAddress::allowed($issuer)) {
                throw ValidationException::withMessages(['issuer' => __('sso.errors.address')]);
            }

            $settings = ['client_id' => $clientId, ...$this->guard('issuer', fn (): array => $this->oidc->discover($issuer))];
            $settings['issuer'] = $issuer;
        } else {
            $xml = (string) ($input['metadata_xml'] ?? '');
            $url = trim((string) ($input['metadata_url'] ?? ''));

            if ($xml === '' && $url !== '') {
                $xml = $this->guard('metadata_url', fn (): string => $this->fetchMetadata($url));
            }

            if ($xml === '') {
                if ($existing?->protocol !== SsoProtocol::Saml) {
                    throw ValidationException::withMessages(['metadata_url' => __('sso.errors.metadataNeeded')]);
                }

                $settings = $existing->settings;
            } else {
                $settings = [...$this->guard($url !== '' ? 'metadata_url' : 'metadata_xml', fn (): array => $this->saml->parseMetadata($xml)), 'metadata_url' => $url !== '' ? $url : null];
            }
        }

        return DB::transaction(function () use ($tenant, $protocol, $settings, $secret, $existing, $actor): TenantSso {
            $before = $existing === null ? null : $this->describe($existing);
            $secretChanged = $protocol === SsoProtocol::Oidc && $secret !== $existing?->client_secret;

            $sso = $this->tenancy->run($tenant->id, function () use ($tenant, $protocol, $settings, $secret, $existing, $actor): TenantSso {
                $sso = $existing ?? new TenantSso(['organisation_id' => $tenant->id]);
                $changed = $existing === null || $existing->protocol !== $protocol || $existing->settings != $settings || $existing->client_secret !== $secret;
                $sso->fill([
                    'protocol' => $protocol,
                    'settings' => $settings,
                    'client_secret' => $secret,
                    'updated_by' => $actor->id,
                ]);

                if ($changed) {
                    // What members use is always what an admin tested.
                    $sso->fill(['status' => SsoStatus::Draft, 'enabled_at' => null, 'last_test_at' => null, 'last_test_passed' => null, 'last_test_detail' => null]);
                }

                $sso->save();

                return $sso;
            });

            $this->audit->record('sso.updated', self::AUDIT_SUBJECT, (string) $tenant->id, [
                'before' => $before,
                'after' => $this->describe($sso),
                'secret_changed' => $secretChanged,
            ], $actor, $tenant->id);

            return $sso;
        });
    }

    /** The REOS test identity provider's settings, for trying SSO out (outside production). */
    public function useTestProvider(Organisation $tenant, SsoProtocol $protocol, User $actor): TenantSso
    {
        return $protocol === SsoProtocol::Oidc
            ? $this->save($tenant, ['protocol' => 'oidc', 'issuer' => TestIdentityProvider::ISSUER, 'client_id' => TestIdentityProvider::CLIENT_ID, 'client_secret' => TestIdentityProvider::CLIENT_SECRET], $actor)
            : $this->save($tenant, ['protocol' => 'saml', 'metadata_xml' => $this->testIdp->samlMetadata()], $actor);
    }

    public function recordTest(Organisation $tenant, bool $passed, string $detail, ?User $actor): void
    {
        $sso = $this->of($tenant) ?? throw new SsoProblem('sso.errors.notSetUp');

        $this->tenancy->run($tenant->id, function () use ($sso, $passed, $detail): void {
            $sso->forceFill([
                'last_test_at' => now(),
                'last_test_passed' => $passed,
                'last_test_detail' => mb_substr($detail, 0, 500),
                // A failed test doesn't switch off SSO members already use.
                'status' => $passed && $sso->status === SsoStatus::Draft ? SsoStatus::Tested : $sso->status,
            ])->save();
        });

        $this->audit->record('sso.tested', self::AUDIT_SUBJECT, (string) $tenant->id, ['passed' => $passed, 'detail' => mb_substr($detail, 0, 500)], $actor, $tenant->id);
    }

    public function enable(Organisation $tenant, User $actor): TenantSso
    {
        $sso = $this->of($tenant);

        if ($sso === null || $sso->status === SsoStatus::Draft) {
            throw ValidationException::withMessages(['sso' => __('sso.errors.testFirst')]);
        }

        if ($sso->status !== SsoStatus::On) {
            $this->tenancy->run($tenant->id, fn () => $sso->forceFill(['status' => SsoStatus::On, 'enabled_at' => now()])->save());
            $this->audit->record('sso.enabled', self::AUDIT_SUBJECT, (string) $tenant->id, ['protocol' => $sso->protocol->value], $actor, $tenant->id);
        }

        return $sso;
    }

    public function disable(Organisation $tenant, User $actor): void
    {
        $sso = $this->of($tenant);

        if ($sso !== null && $sso->status === SsoStatus::On) {
            $this->tenancy->run($tenant->id, fn () => $sso->forceFill(['status' => SsoStatus::Tested, 'enabled_at' => null])->save());
            $this->audit->record('sso.disabled', self::AUDIT_SUBJECT, (string) $tenant->id, [], $actor, $tenant->id);
        }
    }

    /**
     * The REOS account for someone the tenant's provider vouched for:
     *
     * 1. their account already linked to this provider account;
     * 2. else the account with that email, but only if it already belongs to
     *    this tenant or one of its brokerages, schools or teams. An identity
     *    provider is trusted for its own members only: it can never take
     *    over an account it doesn't manage (another tenant's member, a REOS
     *    super admin);
     * 3. else a new account with the email verified and the tenant's member
     *    role (see {@see memberRole()}).
     *
     * @return array{user: User, created: bool}
     */
    public function signIn(Organisation $tenant, SsoClaims $claims): array
    {
        return $this->tenancy->asPlatform(fn (): array => DB::transaction(function () use ($tenant, $claims): array {
            $linked = SsoIdentity::query()->where('organisation_id', $tenant->id)->where('subject', $claims->subject)->first();

            if ($linked !== null) {
                $linked->forceFill(['email' => $claims->email ?? $linked->email, 'last_signed_in_at' => now()])->save();

                return ['user' => $linked->user, 'created' => false];
            }

            if ($claims->email === null) {
                throw new SsoProblem('sso.errors.noEmail', 'Provider sent no email');
            }

            $user = User::query()->whereRaw('lower(email) = ?', [$claims->email])->lockForUpdate()->first();
            $created = $user === null;

            if ($user !== null && ! $this->belongsTo($user, $tenant)) {
                throw new SsoProblem('sso.errors.emailInUse', 'Email belongs to an account outside this tenant');
            }

            if ($user === null) {
                $user = User::query()->create(['name' => $claims->name ?? $claims->email, 'email' => $claims->email, 'locale' => Locale::current()]);
                $user->forceFill(['email_verified_at' => now()])->save();
                [$role, $organisation] = $this->memberRole($tenant);
                $this->memberships->attach($user, $role, $organisation);
            }

            SsoIdentity::query()->create([
                'organisation_id' => $tenant->id,
                'user_id' => $user->id,
                'subject' => $claims->subject,
                'email' => $claims->email,
                'last_signed_in_at' => now(),
            ]);

            $this->audit->record($created ? 'sso.account_created' : 'sso.account_linked', self::AUDIT_SUBJECT, (string) $tenant->id, [
                'user_id' => $user->id,
                'email' => $claims->email,
            ], $user, $tenant->id);

            return ['user' => $user, 'created' => $created];
        }));
    }

    /**
     * The role a person gets on their first single sign-on. Association
     * members are agents across REOS (independent until member sync or an
     * invite places them in their brokerage, A4.3); the SSO link records
     * their association.
     *
     * @return array{0: Role, 1: Organisation}
     */
    public function memberRole(Organisation $tenant): array
    {
        return match ($tenant->type) {
            OrganisationType::School => [Role::Candidate, $tenant],
            OrganisationType::ProviderBusiness => [Role::Provider, $tenant],
            OrganisationType::Brokerage, OrganisationType::Team => [Role::Agent, $tenant],
            default => [Role::Agent, Organisation::platform()],
        };
    }

    /** Whether the person holds a role in this tenant or anywhere below it. */
    public function belongsTo(User $user, Organisation $tenant): bool
    {
        foreach ($this->memberships->of($user) as $membership) {
            if ($membership->role === Role::SuperAdmin) {
                return false;
            }

            for ($current = $membership->organisation, $depth = 0; $current !== null && $depth < 5; $current = $current->parent, $depth++) {
                if ($current->id === $tenant->id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * What the screen and the audit log show: never the secret.
     *
     * @return array<string, mixed>
     */
    public function describe(TenantSso $sso): array
    {
        return [
            'protocol' => $sso->protocol->value,
            'status' => $sso->status->value,
            ...array_intersect_key($sso->settings, array_flip(['issuer', 'client_id', 'entity_id', 'sso_url', 'metadata_url'])),
        ];
    }

    private function fetchMetadata(string $url): string
    {
        if (! ProviderAddress::allowed($url)) {
            throw new SsoProblem('sso.errors.address', "Address not allowed: {$url}");
        }

        try {
            $response = $this->http->connectTimeout(5)->timeout(10)->withOptions(['allow_redirects' => false])->get($url);
        } catch (ConnectionException) {
            throw new SsoProblem('sso.errors.unreachable', "Unreachable: {$url}");
        }

        if (! $response->successful() || strlen($response->body()) > 1_000_000) {
            throw new SsoProblem('sso.errors.metadata', "No metadata at {$url}");
        }

        return $response->body();
    }

    /**
     * Runs a step that reads the provider's details, turning problems into a
     * message on the field the admin filled in.
     *
     * @template T
     *
     * @param  \Closure(): T  $step
     * @return T
     */
    private function guard(string $field, \Closure $step): mixed
    {
        try {
            return $step();
        } catch (SsoProblem $e) {
            throw ValidationException::withMessages([$field => __($e->reason)]);
        }
    }
}
