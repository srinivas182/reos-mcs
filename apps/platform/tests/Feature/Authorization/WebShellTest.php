<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shares the state chip with signed-in people only', function () {
    $this->actingAs(User::factory()->withRole(Role::Agent)->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('region', ['code' => 'TX', 'name' => 'Texas']));

    auth()->logout();
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('region', null));
});

it('keeps the super admin back office out of Settings', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get('/admin/integrations')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/integrations/index'));
    $this->get('/admin/people')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/people/index'));
    $this->get('/settings/integrations')->assertNotFound();
    $this->get('/settings/people')->assertNotFound();
});
