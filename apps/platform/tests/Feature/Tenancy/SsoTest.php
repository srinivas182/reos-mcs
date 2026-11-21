<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\SsoIdentity;
use App\Models\TenantSso;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Services\Tenancy\SingleSignOn;
use App\Sso\OidcClient;
use App\Sso\ProviderAddress;
use App\Sso\SsoProblem;
use App\Sso\SsoProtocol;
use App\Sso\SsoStatus;
use App\Sso\TestIdentityProvider;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(RefreshDatabase::class);

/** Single sign-on (A4.2c, journey screens 2 and 57). */
const TR = 'http://texas-realtors.reos.com';

function ssoAssociation(): Organisation
{
    return Organisation::factory()->ofType(OrganisationType::Association)->create(['name' => 'Texas REALTORS®', 'slug' => 'texas-realtors']);
}

function ssoAdmin(Organisation $association): User
{
    return User::factory()->withRole(Role::AssociationAdmin, $association)->withTwoFactor()->create(['name' => 'Karen Webb', 'email' => 'karen.webb@texasrealtors.example']);
}

function ssoSettings(Organisation $tenant): ?TenantSso
{
    return app(SingleSignOn::class)->of($tenant);
}

/** Set up with the test provider, pass a test and turn it on, as Karen would. */
function ssoOn(TestCase $test, Organisation $tenant, SsoProtocol $protocol): User
{
    $karen = ssoAdmin($tenant);
    $sso = app(SingleSignOn::class);
    $sso->useTestProvider($tenant, $protocol, $karen);
    $sso->recordTest($tenant, true, 'karen.webb@texasrealtors.example', $karen);
    $sso->enable($tenant, $karen);

    return $karen;
}

/** Follows redirects that stay inside REOS until one leaves for the identity provider or ends. */
function followSso(TestCase $test, TestResponse $response): TestResponse
{
    for ($i = 0; $i < 6 && $response->isRedirect(); $i++) {
        $next = (string) $response->headers->get('Location');

        if (str_contains($next, '/sso-test-idp/') || str_contains($next, '/sso/complete/') || ! str_contains($next, '/sso/')) {
            break;
        }

        $response = $test->get($next);
    }

    return $response;
}

/**
 * The test identity provider answers a SAML request at `$idpUrl` for this
 * person. `$change` adjusts the answer (wrong key, tampering, replays).
 *
 * @param  array{key?: string, certificate?: string, sign?: bool, assertionId?: string, inResponseTo?: string|null, audience?: string, acs?: string, tamper?: callable(string): string}  $change
 */
function samlAnswer(TestCase $test, string $idpUrl, string $email, ?string $name = null, array $change = []): TestResponse
{
    parse_str((string) parse_url($idpUrl, PHP_URL_QUERY), $query);
    $idp = app(TestIdentityProvider::class);
    $request = $idp->readRequest((string) $query['SAMLRequest']);
    expect($request)->not->toBeNull();

    $answer = $idp->samlResponse($change['acs'] ?? $request['acs'], $change['audience'] ?? $request['issuer'], array_key_exists('inResponseTo', $change) ? $change['inResponseTo'] : $request['id'], $email, $name, $change['key'] ?? null, $change['certificate'] ?? null, $change['sign'] ?? true, $change['assertionId'] ?? null);

    if (isset($change['tamper'])) {
        $answer = base64_encode($change['tamper'](base64_decode($answer)));
    }

    // The provider posts from its own site; REOS continues with a same-site GET.
    $posted = $test->post($request['acs'], ['SAMLResponse' => $answer, 'RelayState' => $query['RelayState']]);
    $posted->assertStatus(303);

    return followSso($test, $test->get((string) $posted->headers->get('Location')));
}

function oidcAnswer(TestCase $test, string $authorizeUrl, string $email, ?string $name = null): TestResponse
{
    parse_str((string) parse_url($authorizeUrl, PHP_URL_QUERY), $query);
    $code = app(TestIdentityProvider::class)->issueCode($query, $email, $name);

    return followSso($test, $test->get($query['redirect_uri'].'?'.http_build_query(['code' => $code, 'state' => $query['state']])));
}

describe('setting it up (screen 57)', function () {
    it('saves SAML from an uploaded metadata file, shows REOS’s values, and audits without secrets', function () {
        $tr = ssoAssociation();
        $karen = ssoAdmin($tr);
        $xml = app(TestIdentityProvider::class)->samlMetadata();

        $this->actingAs($karen)->put('/organisation/sso', ['protocol' => 'saml', 'metadata_file' => UploadedFile::fake()->createWithContent('idp.xml', $xml)])
            ->assertSessionHasNoErrors()->assertRedirect();

        expect(ssoSettings($tr)?->status)->toBe(SsoStatus::Draft)
            ->and(ssoSettings($tr)?->setting('entity_id'))->toBe(TestIdentityProvider::SAML_ENTITY_ID)
            ->and(ssoSettings($tr)?->certificates())->toHaveCount(1);

        $this->actingAs($karen)->get('/organisation/branding')->assertInertia(fn (Assert $page) => $page
            ->where('sso.settings.protocol', 'saml')
            ->where('sso.settings.status', 'draft')
            ->where('sso.settings.test_provider', true)
            ->where('sso.service_provider.acs_url', TR.'/sso/texas-realtors/callback')
            ->where('sso.service_provider.entity_id', TR.'/sso/texas-realtors/metadata'));
        expect(AuditEvent::query()->where('action', 'sso.updated')->sole()->organisation_id)->toBe($tr->id);
    });

    it('reads SAML metadata from a URL', function () {
        Http::fake(['https://login.texasrealtors.example/saml/metadata' => Http::response(app(TestIdentityProvider::class)->samlMetadata())]);
        $tr = ssoAssociation();

        $this->actingAs(ssoAdmin($tr))->put('/organisation/sso', ['protocol' => 'saml', 'metadata_url' => 'https://login.texasrealtors.example/saml/metadata'])
            ->assertSessionHasNoErrors();

        expect(ssoSettings($tr)?->setting('metadata_url'))->toBe('https://login.texasrealtors.example/saml/metadata');
    });

    it('refuses metadata that isn’t usable and addresses it can’t read', function () {
        Http::fake(['*' => Http::response('<html>Not metadata</html>')]);
        $karen = ssoAdmin(ssoAssociation());

        $this->actingAs($karen)->put('/organisation/sso', ['protocol' => 'saml', 'metadata_url' => 'https://login.texasrealtors.example/saml/metadata'])
            ->assertSessionHasErrors(['metadata_url' => __('sso.errors.metadata')]);
        $this->actingAs($karen)->put('/organisation/sso', ['protocol' => 'saml'])->assertSessionHasErrors('metadata_url');
        $this->actingAs($karen)->put('/organisation/sso', ['protocol' => 'oidc', 'issuer' => 'https://login.texasrealtors.example', 'client_id' => 'reos'])
            ->assertSessionHasErrors(['client_secret' => __('sso.errors.secret')]);
    });

    it('saves OpenID Connect from the provider’s discovery document, keeping the secret out of view', function () {
        Http::fake(['https://login.texasrealtors.example/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://login.texasrealtors.example',
            'authorization_endpoint' => 'https://login.texasrealtors.example/authorize',
            'token_endpoint' => 'https://login.texasrealtors.example/token',
            'jwks_uri' => 'https://login.texasrealtors.example/keys',
        ])]);
        $tr = ssoAssociation();
        $karen = ssoAdmin($tr);

        $this->actingAs($karen)->put('/organisation/sso', ['protocol' => 'oidc', 'issuer' => 'https://login.texasrealtors.example/', 'client_id' => 'reos-learning', 'client_secret' => 's3cret-value'])
            ->assertSessionHasNoErrors();

        $sso = ssoSettings($tr);
        expect($sso?->setting('token_endpoint'))->toBe('https://login.texasrealtors.example/token')
            ->and($sso?->client_secret)->toBe('s3cret-value');
        $stored = app(Tenancy::class)->asPlatform(fn () => DB::table('tenant_sso')->value('client_secret'));
        expect($stored)->not->toContain('s3cret-value');

        $this->actingAs($karen)->get('/organisation/branding')->assertInertia(fn (Assert $page) => $page
            ->where('sso.settings.has_secret', true)
            ->missing('sso.settings.client_secret'));
        expect(json_encode(AuditEvent::query()->where('action', 'sso.updated')->sole()->properties))->not->toContain('s3cret-value');

        // Blank keeps the saved secret.
        $this->actingAs($karen)->put('/organisation/sso', ['protocol' => 'oidc', 'issuer' => 'https://login.texasrealtors.example', 'client_id' => 'reos-learning-2'])->assertSessionHasNoErrors();
        expect(ssoSettings($tr)?->client_secret)->toBe('s3cret-value');
    });

    it('can’t be turned on before a test sign-in passes, and goes off again when settings change', function () {
        $tr = ssoAssociation();
        $karen = ssoAdmin($tr);
        $this->actingAs($karen)->post('/organisation/sso/test-provider', ['protocol' => 'saml']);

        $this->actingAs($karen)->post('/organisation/sso/enable')->assertSessionHasErrors(['sso' => __('sso.errors.testFirst')]);

        app(SingleSignOn::class)->recordTest($tr, true, 'ok', $karen);
        $this->actingAs($karen)->post('/organisation/sso/enable')->assertSessionHasNoErrors();
        expect(ssoSettings($tr)?->status)->toBe(SsoStatus::On);

        $this->actingAs($karen)->post('/organisation/sso/test-provider', ['protocol' => 'oidc']);
        expect(ssoSettings($tr)?->status)->toBe(SsoStatus::Draft);

        $this->actingAs($karen)->post('/organisation/sso/disable');
        expect(AuditEvent::query()->whereIn('action', ['sso.enabled', 'sso.tested'])->pluck('action')->all())->toEqualCanonicalizing(['sso.tested', 'sso.enabled']);
    });

    it('is for the tenant’s admins only', function () {
        $tr = ssoAssociation();
        $this->actingAs(User::factory()->withRole(Role::Agent)->create())->put('/organisation/sso', ['protocol' => 'saml'])->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->post('/organisation/sso/test-provider')->assertNotFound();
        expect(ssoSettings($tr))->toBeNull();
    });

    it('publishes REOS’s SAML metadata for the provider’s admin', function () {
        ssoAssociation();

        $this->get(TR.'/sso/texas-realtors/metadata')->assertOk()
            ->assertHeader('Content-Type', 'application/samlmetadata+xml')
            ->assertSee('Location="'.TR.'/sso/texas-realtors/callback"', false)
            ->assertSee('entityID="'.TR.'/sso/texas-realtors/metadata"', false);
    });
});

describe('test sign-in', function () {
    it('runs the real flow and records who the provider sent, without signing anyone in', function () {
        $tr = ssoAssociation();
        $karen = ssoAdmin($tr);
        $this->actingAs($karen)->post('/organisation/sso/test-provider', ['protocol' => 'saml']);

        $toIdp = followSso($this, $this->actingAs($karen)->post(TR.'/organisation/sso/test'));
        expect((string) $toIdp->headers->get('Location'))->toContain('/sso-test-idp/saml?SAMLRequest=');

        $done = samlAnswer($this, (string) $toIdp->headers->get('Location'), 'karen.webb@texasrealtors.example', 'Karen Webb');
        $done->assertRedirect(TR.'/organisation/branding');

        expect(ssoSettings($tr)?->status)->toBe(SsoStatus::Tested)
            ->and(ssoSettings($tr)?->last_test_passed)->toBeTrue()
            ->and(ssoSettings($tr)?->last_test_detail)->toBe('karen.webb@texasrealtors.example (Karen Webb)')
            ->and(SsoIdentity::query()->count())->toBe(0)
            ->and(AuditEvent::query()->where('action', 'sso.tested')->sole()->actor_id)->toBe($karen->id);
    });

    it('records a failed test with the reason and stays off', function () {
        $tr = ssoAssociation();
        $karen = ssoAdmin($tr);
        $this->actingAs($karen)->post('/organisation/sso/test-provider', ['protocol' => 'saml']);
        $other = TestIdentityProvider::newKeyPair();

        $toIdp = followSso($this, $this->actingAs($karen)->post(TR.'/organisation/sso/test'));
        samlAnswer($this, (string) $toIdp->headers->get('Location'), 'karen.webb@texasrealtors.example', null, $other)
            ->assertRedirect(TR.'/organisation/branding');

        expect(ssoSettings($tr)?->status)->toBe(SsoStatus::Draft)
            ->and(ssoSettings($tr)?->last_test_passed)->toBeFalse()
            ->and(ssoSettings($tr)?->last_test_detail)->toContain(__('sso.errors.rejected'));
    });

    it('shows its own sign-in page with the journey personas and a new member', function () {
        $tr = ssoAssociation();
        ssoOn($this, $tr, SsoProtocol::Oidc);

        $toIdp = followSso($this, $this->get(TR.'/sso/texas-realtors/start'));
        $this->get((string) $toIdp->headers->get('Location'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/test-identity-provider')
            ->where('protocol', 'oidc')
            ->where('people.0.method', 'get')
            ->where('people.0.action', TR.'/sso/texas-realtors/callback'));
    });
});

describe('signing in (journey screen 2)', function () {
    it('shows the button on the association’s address and on its brokerages’', function () {
        $tr = ssoAssociation();
        Organisation::factory()->create(['name' => 'Lone Star Realty', 'slug' => 'lone-star', 'parent_id' => $tr->id]);

        $this->get(TR.'/login')->assertInertia(fn (Assert $page) => $page->where('sso', null));
        ssoOn($this, $tr, SsoProtocol::Saml);

        $this->get(TR.'/login')->assertInertia(fn (Assert $page) => $page
            ->where('sso.name', 'Texas REALTORS®')
            ->where('sso.url', TR.'/sso/texas-realtors/start'));
        $this->get('http://lone-star.reos.com/login')->assertInertia(fn (Assert $page) => $page->where('sso.url', 'http://lone-star.reos.com/sso/texas-realtors/start'));
        $this->get('http://reos.com/login')->assertInertia(fn (Assert $page) => $page->where('sso', null));
    });

    it('creates a member account on a first SAML sign-in, then signs the same person in again', function () {
        $tr = ssoAssociation();
        ssoOn($this, $tr, SsoProtocol::Saml);

        $toIdp = followSso($this, $this->get(TR.'/sso/texas-realtors/start'));
        samlAnswer($this, (string) $toIdp->headers->get('Location'), 'Maria.Lopez@members.texasrealtors.example', 'Maria Lopez')->assertRedirect();

        $maria = User::query()->where('email', 'maria.lopez@members.texasrealtors.example')->sole();
        $this->assertAuthenticatedAs($maria);
        expect($maria->name)->toBe('Maria Lopez')
            ->and($maria->email_verified_at)->not->toBeNull()
            ->and(app(Memberships::class)->find($maria, Role::Agent, Organisation::platform()->id))->not->toBeNull()
            ->and(AuditEvent::query()->where('action', 'sso.account_created')->sole()->organisation_id)->toBe($tr->id);

        auth()->logout();
        $toIdp = followSso($this, $this->get(TR.'/sso/texas-realtors/start'));
        samlAnswer($this, (string) $toIdp->headers->get('Location'), 'maria.lopez@members.texasrealtors.example', 'Maria Lopez');
        $this->assertAuthenticatedAs($maria);
        expect(User::query()->where('email', 'maria.lopez@members.texasrealtors.example')->count())->toBe(1)
            ->and(app(Tenancy::class)->asPlatform(fn () => SsoIdentity::query()->count()))->toBe(1);
    });

    it('links an existing member of one of its brokerages by email (OIDC), and hands them back to the brokerage’s address', function () {
        $tr = ssoAssociation();
        $lone = Organisation::factory()->create(['name' => 'Lone Star Realty', 'slug' => 'lone-star', 'parent_id' => $tr->id]);
        $james = User::factory()->withRole(Role::Agent, $lone)->create(['name' => 'James Carter', 'email' => 'james@lonestar.example']);
        ssoOn($this, $tr, SsoProtocol::Oidc);

        // Starts on the brokerage's address, hops to the association's for the provider.
        $hop = $this->get('http://lone-star.reos.com/sso/texas-realtors/start');
        expect((string) $hop->headers->get('Location'))->toStartWith(TR.'/sso/texas-realtors/start?hop=');
        $toIdp = followSso($this, $hop);

        $back = oidcAnswer($this, (string) $toIdp->headers->get('Location'), 'JAMES@lonestar.example', 'James Carter');
        $ticket = (string) $back->headers->get('Location');
        expect($ticket)->toStartWith('http://lone-star.reos.com/sso/complete/');

        $this->get($ticket)->assertRedirect();
        $this->assertAuthenticatedAs($james);
        expect(AuditEvent::query()->where('action', 'sso.account_linked')->sole()->properties['user_id'])->toBe($james->id);

        // A ticket works once, and only on the address it was made for.
        auth()->logout();
        $this->get($ticket)->assertRedirect('/login')->assertSessionHasErrors(['sign_in' => __('sso.errors.expired')]);
        $this->assertGuest();
    });

    it('never takes over an account outside the tenant, even with a matching email', function () {
        $tr = ssoAssociation();
        User::factory()->withRole(Role::Agent)->create(['email' => 'pat@elsewhere.example']);
        User::factory()->superAdmin()->create(['email' => 'ops@reos.example']);
        ssoOn($this, $tr, SsoProtocol::Saml);

        foreach (['pat@elsewhere.example', 'ops@reos.example'] as $email) {
            $toIdp = followSso($this, $this->get(TR.'/sso/texas-realtors/start'));
            samlAnswer($this, (string) $toIdp->headers->get('Location'), $email)
                ->assertRedirect('/login')->assertSessionHasErrors(['sign_in' => __('sso.errors.emailInUse')]);
            $this->assertGuest();
        }

        expect(app(Tenancy::class)->asPlatform(fn () => SsoIdentity::query()->count()))->toBe(0);
    });

    it('asks people with two-step on for their code, as after a password', function () {
        $tr = ssoAssociation();
        ssoOn($this, $tr, SsoProtocol::Oidc);

        $toIdp = followSso($this, $this->get(TR.'/sso/texas-realtors/start'));
        oidcAnswer($this, (string) $toIdp->headers->get('Location'), 'karen.webb@texasrealtors.example')->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    });

    it('is refused while single sign-on is off', function () {
        $tr = ssoAssociation();
        $karen = ssoAdmin($tr);
        app(SingleSignOn::class)->useTestProvider($tr, SsoProtocol::Saml, $karen);

        $this->get(TR.'/sso/texas-realtors/start')->assertRedirect('/login')->assertSessionHasErrors(['sign_in' => __('sso.errors.notOn')]);
    });
});

describe('security checks', function () {
    it('refuses SAML answers that are unsigned, signed with another key, altered, aimed elsewhere or replayed', function () {
        // Seven sign-ins in a row: more than the sign-in rate limit allows a minute.
        $this->withoutMiddleware(ThrottleRequests::class);
        $tr = ssoAssociation();
        ssoOn($this, $tr, SsoProtocol::Saml);
        $email = 'maria.lopez@members.texasrealtors.example';
        $refused = function (array $change, string $reason = 'sso.errors.rejected') use ($email): void {
            $toIdp = followSso($this, $this->get(TR.'/sso/texas-realtors/start'));
            samlAnswer($this, (string) $toIdp->headers->get('Location'), $email, null, $change)
                ->assertRedirect('/login')->assertSessionHasErrors(['sign_in' => __($reason)]);
            $this->assertGuest();
        };

        $refused(['sign' => false]);
        $refused(TestIdentityProvider::newKeyPair());
        $refused(['tamper' => fn (string $xml): string => str_replace($email, 'karen.webb@texasrealtors.example', $xml)]);
        $refused(['inResponseTo' => '_not-the-request-REOS-sent']);
        // Properly signed, but for another service or another address.
        $refused(['audience' => 'https://other.example/sp']);
        $refused(['acs' => 'https://other.example/sso/acs']);

        // The same assertion twice: the second is refused even with a fresh request.
        $toIdp = followSso($this, $this->get(TR.'/sso/texas-realtors/start'));
        samlAnswer($this, (string) $toIdp->headers->get('Location'), $email, null, ['assertionId' => '_assertion-used-once']);
        $this->assertAuthenticated();
        auth()->logout();
        $refused(['assertionId' => '_assertion-used-once'], 'sso.errors.replayed');
    });

    it('refuses an answer nobody started here, and the same OIDC answer twice', function () {
        $tr = ssoAssociation();
        ssoOn($this, $tr, SsoProtocol::Oidc);

        $this->get(TR.'/sso/texas-realtors/callback?code=abc&state=forged')->assertRedirect('/login')->assertSessionHasErrors(['sign_in' => __('sso.errors.expired')]);

        $toIdp = followSso($this, $this->get(TR.'/sso/texas-realtors/start'));
        parse_str((string) parse_url((string) $toIdp->headers->get('Location'), PHP_URL_QUERY), $query);
        $callback = $query['redirect_uri'].'?'.http_build_query(['code' => app(TestIdentityProvider::class)->issueCode($query, 'maria@members.example', null), 'state' => $query['state']]);
        $this->get($callback);
        $this->assertAuthenticated();
        auth()->logout();

        $this->get($callback)->assertSessionHasErrors(['sign_in' => __('sso.errors.expired')]);
        $this->assertGuest();
    });

    it('takes the provider’s cross-site POST without touching the person’s session', function () {
        ssoAssociation();

        $posted = $this->post(TR.'/sso/texas-realtors/callback', ['SAMLResponse' => 'PHNhbWw+', 'RelayState' => 'abc', 'extra' => 'dropped']);

        $posted->assertStatus(303);
        expect((string) $posted->headers->get('Location'))->toStartWith(TR.'/sso/texas-realtors/callback?handoff=')
            ->and(collect($posted->headers->getCookies())->map->getName()->all())->not->toContain(config('session.cookie'));
    });

    it('checks OIDC ID tokens: signature, issuer, audience, nonce and expiry', function () {
        $tr = ssoAssociation();
        $sso = app(SingleSignOn::class)->useTestProvider($tr, SsoProtocol::Oidc, ssoAdmin($tr));
        $idp = app(TestIdentityProvider::class);
        $oidc = app(OidcClient::class);
        $claims = ['iss' => TestIdentityProvider::ISSUER, 'aud' => TestIdentityProvider::CLIENT_ID, 'sub' => 'member-1', 'email' => 'Maria@Members.example', 'nonce' => 'n-1', 'iat' => time(), 'exp' => time() + 300];

        expect($oidc->verify($sso, $idp->idToken($claims), 'n-1')->email)->toBe('maria@members.example');

        $bad = [
            'another key' => $idp->idToken($claims, TestIdentityProvider::newKeyPair()['key']),
            'issuer' => $idp->idToken([...$claims, 'iss' => 'https://evil.example']),
            'audience' => $idp->idToken([...$claims, 'aud' => 'someone-else']),
            'expired' => $idp->idToken([...$claims, 'exp' => time() - 600]),
        ];

        foreach ($bad as $why => $token) {
            expect(fn () => $oidc->verify($sso, $token, 'n-1'))->toThrow(SsoProblem::class);
        }

        expect(fn () => $oidc->verify($sso, $idp->idToken($claims), 'another-nonce'))->toThrow(SsoProblem::class)
            ->and($oidc->verify($sso, $idp->idToken([...$claims, 'email_verified' => false]), 'n-1')->email)->toBeNull();
    });

    it('verifies a real provider’s tokens with the keys it publishes', function () {
        $idp = app(TestIdentityProvider::class);
        Http::fake([
            'https://login.texasrealtors.example/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://login.texasrealtors.example',
                'authorization_endpoint' => 'https://login.texasrealtors.example/authorize',
                'token_endpoint' => 'https://login.texasrealtors.example/token',
                'jwks_uri' => 'https://login.texasrealtors.example/keys',
            ]),
            'https://login.texasrealtors.example/keys' => Http::response($idp->jwks()),
        ]);
        $tr = ssoAssociation();
        $sso = app(SingleSignOn::class)->save($tr, ['protocol' => 'oidc', 'issuer' => 'https://login.texasrealtors.example', 'client_id' => 'reos', 'client_secret' => 'x'], ssoAdmin($tr));

        $token = $idp->idToken(['iss' => 'https://login.texasrealtors.example', 'aud' => 'reos', 'sub' => 'm-9', 'email' => 'm@example.org', 'nonce' => 'n', 'iat' => time(), 'exp' => time() + 300]);

        expect(app(OidcClient::class)->verify($sso, $token, 'n')->subject)->toBe('m-9');
    });

    it('only fetches public HTTPS addresses in production', function () {
        expect(ProviderAddress::allowed('https://93.184.215.14/metadata', strict: true))->toBeTrue()
            ->and(ProviderAddress::allowed('http://93.184.215.14/metadata', strict: true))->toBeFalse()
            ->and(ProviderAddress::allowed('https://127.0.0.1/metadata', strict: true))->toBeFalse()
            ->and(ProviderAddress::allowed('https://10.0.0.5/metadata', strict: true))->toBeFalse()
            ->and(ProviderAddress::allowed('https://169.254.169.254/latest', strict: true))->toBeFalse()
            ->and(ProviderAddress::allowed('https://user:pw@93.184.215.14/', strict: true))->toBeFalse()
            ->and(ProviderAddress::allowed('http://localhost:8080/metadata', strict: false))->toBeTrue();
    });

    it('keeps the test identity provider out of production', function () {
        app()->detectEnvironment(fn () => 'production');

        expect(app(TestIdentityProvider::class)->handles(TestIdentityProvider::ISSUER))->toBeFalse();
        $this->get('/sso-test-idp/authorize?client_id=reos-test')->assertNotFound();
    });
});
