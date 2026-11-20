<?php

declare(strict_types=1);

use App\Integrations\Catalogue;
use App\Integrations\IntegrationMode;
use App\Listeners\RecordWebSignIn;
use App\Models\User;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows only the sign-in options that can work', function () {
    app(IntegrationHub::class)->setMode(app(Catalogue::class)->find('apple-sign-in'), IntegrationMode::Off, null);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->component('auth/login')
        ->where('signInOptions', ['google' => true, 'apple' => false, 'phone' => true]));

    app()->detectEnvironment(fn () => 'production');

    // On demo data in production, nothing pretends to work.
    $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
        ->where('signInOptions', ['google' => false, 'apple' => false, 'phone' => false]));
});

it('remembers where the phone code went, so the code box shows', function () {
    $this->post(route('auth.phone.start'), ['phone' => '+15125550100']);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('phoneCodeSentTo', '+15125550100'));
});

it('lists phones and browsers on the Security page, and signs a phone out', function () {
    $user = User::factory()->create();
    $token = $user->createToken("James's iPhone")->accessToken;
    $token->forceFill(['platform' => 'ios'])->save();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasPassword', true)
            ->where('devices.0.name', "James's iPhone")
            ->where('devices.0.current', false));

    $this->actingAs($user)->delete(route('devices.destroy', $token->id))->assertRedirect();

    expect($user->tokens()->count())->toBe(0);
});

it('cannot sign out someone else’s phone from the web', function () {
    $other = User::factory()->create()->createToken('Their phone')->accessToken;

    $this->actingAs(User::factory()->create())->delete(route('devices.destroy', $other->id))->assertNotFound();
});

it('lets people without a password add one without a current password', function () {
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)->put(route('user-password.update'), [
        'password' => 'a long new pass phrase',
        'password_confirmation' => 'a long new pass phrase',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()?->password)->not->toBeNull();
});

it('asks people without a password to have signed in recently for sensitive pages', function () {
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)->withSession([RecordWebSignIn::SIGNED_IN_AT => now()->getTimestamp()])
        ->get(route('security.edit'))->assertOk();

    $this->travel(16)->minutes();

    $this->get(route('security.edit'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'For your safety, please sign in again to open that page.');
    $this->assertGuest();
});
