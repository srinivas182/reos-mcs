<?php

declare(strict_types=1);

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function signIn(object $test, string $deviceId, string $name): string
{
    return $test->postJson('/api/v1/auth/google', [
        'id_token' => 'simulator:james.miller@demo.reos.test',
        'device' => ['id' => $deviceId, 'name' => $name, 'platform' => 'ios'],
    ])->json('token');
}

it('lists signed-in devices and marks this one', function () {
    signIn($this, '11111111-1111-4111-8111-111111111111', 'Old iPad');
    $token = signIn($this, '22222222-2222-4222-8222-222222222222', "James's iPhone");

    $this->withToken($token)->getJson('/api/v1/me/devices')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['name' => "James's iPhone", 'current' => true])
        ->assertJsonFragment(['name' => 'Old iPad', 'current' => false]);
});

it('signs out another device, which then stops working', function () {
    $ipad = signIn($this, '11111111-1111-4111-8111-111111111111', 'Old iPad');
    $phone = signIn($this, '22222222-2222-4222-8222-222222222222', "James's iPhone");
    $ipadId = collect($this->withToken($phone)->getJson('/api/v1/me/devices')->json('data'))->firstWhere('name', 'Old iPad')['id'];

    $this->withToken($phone)->deleteJson("/api/v1/me/devices/{$ipadId}")->assertNoContent();

    app('auth')->forgetGuards();
    $this->withToken($ipad)->getJson('/api/v1/me')->assertUnauthorized();
    expect(AuditEvent::query()->where('action', 'auth.device_revoked')->count())->toBe(1);
});

it('cannot sign out someone else’s device', function () {
    $mine = signIn($this, '11111111-1111-4111-8111-111111111111', 'My phone');
    $other = User::factory()->create()->createToken('Their phone')->accessToken;

    $this->withToken($mine)->deleteJson("/api/v1/me/devices/{$other->id}")->assertNotFound();
});

it('signs out this device', function () {
    $token = signIn($this, '11111111-1111-4111-8111-111111111111', 'My phone');

    $this->withToken($token)->postJson('/api/v1/auth/sign-out')->assertNoContent();

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('signs out a device after 30 days unused', function () {
    $token = signIn($this, '11111111-1111-4111-8111-111111111111', 'My phone');

    $this->travel(31)->days();

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});
