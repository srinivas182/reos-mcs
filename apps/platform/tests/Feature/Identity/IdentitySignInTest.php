<?php

declare(strict_types=1);

use App\Integrations\Catalogue;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\IntegrationCheck;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\NewDeviceSignIn;
use App\Services\Integrations\IntegrationHub;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

const GOOGLE_CLIENT = '418202517-web.apps.googleusercontent.com';

beforeEach(function () {
    Http::preventStrayRequests();
    Notification::fake();
});

/** A fresh RSA key pair and its published JWKS, like Google's. */
function signingKey(): array
{
    static $key = null;

    if ($key === null) {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);
        $b64 = fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $key = [
            'private' => $private,
            'jwks' => ['keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'use' => 'sig', 'alg' => 'RS256', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]]],
        ];
    }

    return $key;
}

function googleToken(array $claims = []): string
{
    return JWT::encode([
        'iss' => 'https://accounts.google.com',
        'aud' => GOOGLE_CLIENT,
        'sub' => '1098765',
        'email' => 'sofia.reyes@example.com',
        'email_verified' => true,
        'name' => 'Sofia Reyes',
        'iat' => time(),
        'exp' => time() + 3600,
        ...$claims,
    ], signingKey()['private'], 'RS256', 'test-key');
}

/** Switches Google sign-in to run live on our test client id. */
function googleLive(): void
{
    Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response(signingKey()['jwks'])]);
    app(CredentialVault::class)->put('google-sign-in', CredentialEnvironment::Live, ['client_id' => GOOGLE_CLIENT, 'client_secret' => 'fake-secret'], null);
    IntegrationCheck::query()->create(['integration_id' => 'google-sign-in', 'environment' => 'live', 'outcome' => 'passed',
        'message' => 'integrations.checks.passed', 'parameters' => [], 'trigger' => 'manual', 'created_at' => now()->addMinute()]);
    app(IntegrationHub::class)->setMode(app(Catalogue::class)->find('google-sign-in'), IntegrationMode::Live, null);
}

function device(array $overrides = []): array
{
    return ['id' => '0f8fad5b-d9cb-469f-a165-70867728950e', 'name' => "Sofia's iPhone", 'platform' => 'ios', 'app_version' => '1.0.0', ...$overrides];
}

it('creates the account on first Google sign-in and returns a device token', function () {
    googleLive();

    $response = $this->postJson('/api/v1/auth/google', ['id_token' => googleToken(), 'device' => device()])
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('new_account', true)
        ->assertJsonPath('new_device', false)
        ->assertJsonPath('user.name', 'Sofia Reyes')
        ->assertJsonPath('user.email', 'sofia.reyes@example.com')
        ->assertJsonPath('user.email_verified', true)
        ->assertJsonPath('user.sign_in_methods', ['google']);

    $this->withToken($response->json('token'))->getJson('/api/v1/me')
        ->assertOk()->assertJsonPath('email', 'sofia.reyes@example.com');

    expect(AuditEvent::query()->where('action', 'auth.signed_in')->sole()->properties)->toMatchArray(['method' => 'google', 'new_account' => true]);
});

it('joins an existing account with the same verified email instead of making a second one', function () {
    googleLive();
    $existing = User::factory()->create(['email' => 'Sofia.Reyes@example.com']);

    $this->postJson('/api/v1/auth/google', ['id_token' => googleToken(), 'device' => device()])
        ->assertOk()->assertJsonPath('new_account', false)->assertJsonPath('user.id', $existing->id);

    expect(User::query()->count())->toBe(1)
        ->and(SocialAccount::query()->sole()->user_id)->toBe($existing->id);
});

it('drops an unproven password when Google proves who owns the email', function () {
    googleLive();
    $squatter = User::factory()->unverified()->create(['email' => 'sofia.reyes@example.com']);

    $this->postJson('/api/v1/auth/google', ['id_token' => googleToken(), 'device' => device()])->assertOk();

    $squatter->refresh();
    expect($squatter->password)->toBeNull()
        ->and($squatter->email_verified_at)->not->toBeNull();
});

it('never joins accounts on an unverified email', function () {
    googleLive();
    User::factory()->create(['email' => 'sofia.reyes@example.com']);

    $this->postJson('/api/v1/auth/google', ['id_token' => googleToken(['email_verified' => false]), 'device' => device()])
        ->assertOk()->assertJsonPath('new_account', true)->assertJsonPath('user.email', null);

    expect(User::query()->count())->toBe(2);
});

it('rejects tokens that are forged, expired or issued for another app', function (array $claims, ?string $key) {
    googleLive();
    $token = $key === 'other'
        ? JWT::encode(['iss' => 'https://accounts.google.com', 'aud' => GOOGLE_CLIENT, 'sub' => '1', 'exp' => time() + 60], openssl_pkey_get_private(openssl_pkey_new()), 'RS256', 'test-key')
        : googleToken($claims);

    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device' => device()])
        ->assertUnauthorized()
        ->assertJson(['reason' => 'identity.errors.invalidToken', 'message' => "We couldn't confirm your sign-in. Please try again."]);

    expect(User::query()->count())->toBe(0);
})->with([
    'another app' => [['aud' => 'someone-else.apps.googleusercontent.com'], null],
    'expired' => [['exp' => time() - 3600, 'iat' => time() - 7200], null],
    'wrong issuer' => [['iss' => 'https://evil.example.com'], null],
    'not signed by Google' => [[], 'other'],
]);

it('says so plainly when a sign-in option is switched off', function () {
    app(IntegrationHub::class)->setMode(app(Catalogue::class)->find('apple-sign-in'), IntegrationMode::Off, null);

    $this->postJson('/api/v1/auth/apple', ['id_token' => 'x', 'device' => device()])
        ->assertServiceUnavailable()
        ->assertJsonPath('reason', 'identity.errors.unavailable');
});

it('lets every persona be tried on demo data, outside production only', function () {
    $this->postJson('/api/v1/auth/apple', ['id_token' => 'simulator:dana.brooks@demo.reos.test', 'name' => 'Dana Brooks', 'device' => device()])
        ->assertOk()->assertJsonPath('user.name', 'Dana Brooks');

    app()->detectEnvironment(fn () => 'production');

    $this->postJson('/api/v1/auth/apple', ['id_token' => 'simulator:anyone@example.com', 'device' => device(['id' => '6f1c1b0e-3c4b-4b8e-9d0f-0a1b2c3d4e5f'])])
        ->assertServiceUnavailable();
});

it('validates the device that is signing in', function () {
    $this->postJson('/api/v1/auth/google', ['id_token' => 'simulator:a@b.co', 'device' => ['id' => 'not-a-uuid', 'platform' => 'windows']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device.id', 'device.name', 'device.platform']);
});

it('emails people when they sign in on a phone they have not used before', function () {
    $sign = fn (array $device) => $this->postJson('/api/v1/auth/google', ['id_token' => 'simulator:sofia@example.com', 'device' => $device])->assertOk();

    $sign(device());
    Notification::assertNothingSent();

    // Same phone again: no email, and its old token is replaced.
    $sign(device());
    Notification::assertNothingSent();
    expect(User::query()->sole()->tokens()->count())->toBe(1);

    $sign(device(['id' => '6f1c1b0e-3c4b-4b8e-9d0f-0a1b2c3d4e5f', 'name' => 'Pixel 9', 'platform' => 'android']))
        ->assertJsonPath('new_device', true);
    Notification::assertSentTo(User::query()->sole(), NewDeviceSignIn::class,
        fn (NewDeviceSignIn $n) => $n->toMail(User::query()->sole())->introLines[0] === 'Your account was just signed in on Pixel 9.');
});

it('limits sign-in attempts per network', function () {
    foreach (range(1, 20) as $_) {
        $this->postJson('/api/v1/auth/google', ['id_token' => 'bad', 'device' => device()]);
    }

    $this->postJson('/api/v1/auth/google', ['id_token' => 'bad', 'device' => device()])->assertTooManyRequests();
});
