<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pkce(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

    return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
}

/** The browser part: start from the app, pick a demo persona, come back with a code. */
function browserSignIn(object $test, string $challenge, string $email = 'sofia.reyes@demo.reos.test'): string
{
    $start = $test->get('/auth/google/redirect?app_challenge='.$challenge);
    parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);

    $back = $test->post('/integrations/google-sign-in/callback', ['state' => $query['state'], 'demo_email' => $email]);
    $final = $test->get($back->headers->get('Location'));

    $location = $final->headers->get('Location');
    expect($location)->toStartWith('reos://auth?code=');
    parse_str(parse_url($location, PHP_URL_QUERY), $app);

    return $app['code'];
}

function appDevice(): array
{
    return ['id' => '0f8fad5b-d9cb-469f-a165-70867728950e', 'name' => 'Pixel 9', 'platform' => 'android'];
}

it('signs the app in through the browser with a one-time, PKCE-bound code', function () {
    [$verifier, $challenge] = pkce();
    $code = browserSignIn($this, $challenge);

    $this->assertGuest('web'); // the browser itself isn't signed in

    $this->postJson('/api/v1/auth/exchange', ['code' => $code, 'code_verifier' => $verifier, 'device' => appDevice()])
        ->assertOk()
        ->assertJsonPath('user.email', 'sofia.reyes@demo.reos.test')
        ->assertJsonPath('new_account', true);

    // Codes work once.
    $this->postJson('/api/v1/auth/exchange', ['code' => $code, 'code_verifier' => $verifier, 'device' => appDevice()])
        ->assertUnauthorized();
});

it('refuses a code without the app’s verifier (stolen from the link)', function () {
    [, $challenge] = pkce();
    [$otherVerifier] = pkce();
    $code = browserSignIn($this, $challenge);

    $this->postJson('/api/v1/auth/exchange', ['code' => $code, 'code_verifier' => $otherVerifier, 'device' => appDevice()])
        ->assertUnauthorized();

    expect(User::query()->first()?->tokens()->count())->toBe(0);
});

it('codes expire after two minutes', function () {
    [$verifier, $challenge] = pkce();
    $code = browserSignIn($this, $challenge);

    $this->travel(3)->minutes();

    $this->postJson('/api/v1/auth/exchange', ['code' => $code, 'code_verifier' => $verifier, 'device' => appDevice()])
        ->assertUnauthorized();
});

it('works even when the browser is already signed in to the web app', function () {
    [$verifier, $challenge] = pkce();
    $this->actingAs(User::factory()->create());

    $code = browserSignIn($this, $challenge, 'karen.webb@demo.reos.test');

    $this->postJson('/api/v1/auth/exchange', ['code' => $code, 'code_verifier' => $verifier, 'device' => appDevice()])
        ->assertOk()->assertJsonPath('user.email', 'karen.webb@demo.reos.test');
});

it('sends failures back to the app with a reason', function () {
    [, $challenge] = pkce();
    $this->get('/auth/google/redirect?app_challenge='.$challenge);

    $this->get('/integrations/google-sign-in/callback?state=forged')
        ->assertRedirect('reos://auth?error=identity.errors.invalidToken');
});

it('tells the app which sign-in buttons to show', function () {
    $this->getJson('/api/v1/app-config')
        ->assertOk()
        ->assertJsonPath('sign_in', ['google' => true, 'apple' => true, 'phone' => true, 'demo' => true]);
});
