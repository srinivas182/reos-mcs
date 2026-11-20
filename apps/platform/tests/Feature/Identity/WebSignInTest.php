<?php

declare(strict_types=1);

use App\Identity\WebDevice;
use App\Integrations\Catalogue;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\IntegrationCheck;
use App\Models\User;
use App\Notifications\NewDeviceSignIn;
use App\Services\Integrations\IntegrationHub;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

const WEB_GOOGLE_CLIENT = 'web-client.apps.googleusercontent.com';

beforeEach(function () {
    Http::preventStrayRequests();
    Notification::fake();
});

function webKey(): array
{
    static $key = null;

    if ($key === null) {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);
        $b64 = fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $key = ['private' => $private, 'jwks' => ['keys' => [['kty' => 'RSA', 'kid' => 'k1', 'alg' => 'RS256', 'use' => 'sig', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]]]];
    }

    return $key;
}

function googleOnLive(): void
{
    app(CredentialVault::class)->put('google-sign-in', CredentialEnvironment::Live, ['client_id' => WEB_GOOGLE_CLIENT, 'client_secret' => 'fake-secret'], null);
    IntegrationCheck::query()->create(['integration_id' => 'google-sign-in', 'environment' => 'live', 'outcome' => 'passed',
        'message' => 'integrations.checks.passed', 'parameters' => [], 'trigger' => 'manual', 'created_at' => now()->addMinute()]);
    app(IntegrationHub::class)->setMode(app(Catalogue::class)->find('google-sign-in'), IntegrationMode::Live, null);
}

it('signs in with Google on the web: state, nonce and PKCE checked', function () {
    googleOnLive();

    $redirect = $this->get(route('auth.social.redirect', 'google'))->assertRedirect();
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query)->toMatchArray([
        'client_id' => WEB_GOOGLE_CLIENT,
        'redirect_uri' => url('integrations/google-sign-in/callback'),
        'code_challenge_method' => 'S256',
    ]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['id_token' => JWT::encode([
            'iss' => 'https://accounts.google.com', 'aud' => WEB_GOOGLE_CLIENT, 'sub' => 'g-42',
            'email' => 'linda.park@example.com', 'email_verified' => true, 'name' => 'Linda Park',
            'nonce' => $query['nonce'], 'iat' => time(), 'exp' => time() + 600,
        ], webKey()['private'], 'RS256', 'k1')]),
        'www.googleapis.com/oauth2/v3/certs' => Http::response(webKey()['jwks']),
    ]);

    $this->get('/integrations/google-sign-in/callback?'.http_build_query(['state' => $query['state'], 'code' => 'auth-code']))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs(User::query()->where('email', 'linda.park@example.com')->sole());
    Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/token'
        && $r['code'] === 'auth-code' && strlen((string) $r['code_verifier']) === 64);
    expect(AuditEvent::query()->where('action', 'auth.signed_in')->sole()->properties)
        ->toMatchArray(['method' => 'google', 'platform' => 'web']);
});

it('refuses a callback whose state does not match (forged or replayed)', function () {
    googleOnLive();
    $this->get(route('auth.social.redirect', 'google'));

    $this->get('/integrations/google-sign-in/callback?state=forged&code=x')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sign_in');

    $this->assertGuest();
});

it('lets demo personas sign in on demo data, outside production', function () {
    $redirect = $this->get(route('auth.social.redirect', 'apple'))->assertRedirect();
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->get($redirect->headers->get('Location'))
        ->assertInertia(fn (Assert $page) => $page->component('auth/demo-sign-in')->has('personas', 9));

    // Apple answers with a cross-site POST; it is handed over to a same-site GET.
    $handoff = $this->post('/integrations/apple-sign-in/callback', ['state' => $query['state'], 'demo_email' => 'karen.webb@demo.reos.test'])
        ->assertStatus(303);

    $this->get($handoff->headers->get('Location'))->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

it('sends people with two-step turned on to its challenge first', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-step is off.');
    }

    $user = User::factory()->create(['email' => 'paul.diaz@demo.reos.test', 'email_verified_at' => now()]);
    $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

    $redirect = $this->get(route('auth.social.redirect', 'google'));
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->post('/integrations/google-sign-in/callback', ['state' => $query['state'], 'demo_email' => 'paul.diaz@demo.reos.test']);
    $this->get('/integrations/google-sign-in/callback?handoff=missing');

    $this->assertGuest();
});

it('signs in with a phone code on the web', function () {
    $code = null;
    Log::listen(function ($m) use (&$code) {
        preg_match('/: (\d{6})$/', $m->message, $hit) === 1 && $code = $hit[1];
    });

    $this->post(route('auth.phone.start'), ['phone' => '+15125550123'])->assertSessionHas('phoneCodeSentTo', '+15125550123');

    $this->post(route('auth.phone.verify'), ['phone' => '+15125550123', 'code' => '000000'])
        ->assertRedirect(route('login'))->assertSessionHasErrors('code');

    $this->post(route('auth.phone.verify'), ['phone' => '+15125550123', 'code' => $code])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs(User::query()->where('phone', '+15125550123')->sole());
});

it('treats each browser as a device and emails about new ones', function () {
    $user = User::factory()->create();
    $chrome = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/131.0 Safari/537.36';

    $this->withHeader('User-Agent', $chrome)->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $cookie = collect($this->app['cookie']->getQueuedCookies())->firstWhere(fn ($c) => $c->getName() === WebDevice::COOKIE);
    Notification::assertNothingSent();

    $this->post(route('logout'));
    $this->withCookie(WebDevice::COOKIE, $cookie->getValue())->withHeader('User-Agent', $chrome)
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    Notification::assertNothingSent();

    $this->post(route('logout'));
    $this->defaultCookies = []; // a different browser has no REOS cookie
    $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Gecko/20100101 Firefox/133.0')
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    Notification::assertSentTo($user, NewDeviceSignIn::class, fn (NewDeviceSignIn $n) => $n->device->name === 'Firefox · Windows');
});

it('names browsers without versions', function (string $agent, string $name) {
    expect(WebDevice::name($agent))->toBe($name);
})->with([
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1', 'Safari · iOS'],
    ['Mozilla/5.0 (Linux; Android 15) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36', 'Chrome · Android'],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0 Safari/537.36 Edg/131.0', 'Edge · Windows'],
    ['curl/8.0', 'Browser'],
]);
