<?php

declare(strict_types=1);

use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\AccountDeletionScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

function appSignIn(object $test, string $email = 'james.miller@demo.reos.test'): string
{
    return $test->postJson('/api/v1/auth/google', [
        'id_token' => "simulator:{$email}",
        'device' => ['id' => '0f8fad5b-d9cb-469f-a165-70867728950e', 'name' => 'iPhone', 'platform' => 'ios'],
    ])->json('token');
}

it('deletes after 30 days, signs out everywhere now, and emails the date', function () {
    $token = appSignIn($this);

    $this->withToken($token)->deleteJson('/api/v1/me')->assertNoContent();

    $user = User::query()->sole();
    expect($user->deletion_requested_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0);
    Notification::assertSentTo($user, AccountDeletionScheduled::class);

    $this->travel(29)->days();
    $this->artisan('reos:accounts:purge')->assertSuccessful();
    expect(User::query()->count())->toBe(1);

    $this->travel(2)->days();
    $this->artisan('reos:accounts:purge')->expectsOutputToContain('Deleted 1')->assertSuccessful();
    expect(User::query()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'account.deleted')->sole()->actor_id)->toBeNull();
});

it('keeps the account when the person signs in again within 30 days', function () {
    $this->withToken(appSignIn($this))->deleteJson('/api/v1/me')->assertNoContent();
    $this->travel(10)->days();

    appSignIn($this);

    expect(User::query()->sole()->deletion_requested_at)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'account.deletion_cancelled')->count())->toBe(1);
});

it('asks for a fresh sign-in before deleting from the app', function () {
    $token = appSignIn($this);
    $this->travel(20)->minutes();

    $this->withToken($token)->deleteJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('reason', 'identity.errors.recentSignInRequired');

    expect(User::query()->sole()->deletion_requested_at)->toBeNull();
});

it('lets people without a password confirm on the web by ticking a box', function () {
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)->delete(route('profile.destroy'))->assertSessionHasErrors('confirm');
    $this->actingAs($user)->delete(route('profile.destroy'), ['confirm' => '1'])->assertRedirect('/');

    expect($user->fresh()?->deletion_requested_at)->not->toBeNull();
});

it('signs out other browsers that were signed in before the deletion request', function () {
    $user = User::factory()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->get(route('dashboard'))->assertOk();

    $this->travel(1)->minutes();
    $user->forceFill(['deletion_requested_at' => now()])->save();
    app('auth')->forgetGuards(); // a new request loads the person afresh

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
