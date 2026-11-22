<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Checkout;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\TenantSignup;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;
use Database\Seeders\Demo\DemoTenantPlans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    app(DemoTenantPlans::class)->seed();
    $this->owner = User::factory()->create(['name' => 'Linda Park']);
    $this->starter = Price::query()->whereHas('product', fn ($q) => $q->where('key', 'brokerage-starter'))->sole();
    $this->school = Price::query()->whereHas('product', fn ($q) => $q->where('key', 'school-essentials'))->sole();
});

it('done when: a brokerage signs up and pays on the web on its own', function () {
    $this->actingAs($this->owner)->get('/start/brokerage')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('public/start')
        ->where('state', 'TX')
        ->has('plans', 2)
        ->where('plans.0.product', 'Brokerage Starter')
        ->where('plans.0.seats', 25));

    $this->post('/start/brokerage', ['name' => 'Bluebonnet Realty', 'states' => ['TX'], 'price_id' => $this->starter->id])
        ->assertRedirect();
    $signup = TenantSignup::query()->sole();
    expect($signup->status)->toBe('pending');

    $this->get("/checkout/{$this->starter->id}?signup={$signup->uuid}")->assertInertia(fn (Assert $page) => $page->where('signup.name', 'Bluebonnet Realty'));
    $started = $this->postJson("/checkout/{$this->starter->id}", ['signup' => $signup->uuid])->assertCreated();
    $order = Order::query()->where('uuid', $started->json('order'))->sole();
    $this->post("/checkout/orders/{$order->uuid}/simulate");

    $this->get("/checkout/orders/{$order->uuid}/return")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('order.status', 'paid')
        ->where('tenant.name', 'Bluebonnet Realty'));

    $tenant = Organisation::query()->where('name', 'Bluebonnet Realty')->sole();
    expect($tenant->type)->toBe(OrganisationType::Brokerage)
        ->and($tenant->states)->toBe(['TX'])
        ->and($tenant->tenantLimits()->seats)->toBe(25)
        ->and($signup->refresh())->status->toBe('completed')->created_organisation_id->toBe($tenant->id)
        ->and(collect(app(Memberships::class)->of($this->owner))->contains(fn ($m) => $m->role === Role::Broker && $m->organisation->id === $tenant->id))->toBeTrue()
        ->and(app(ActiveRole::class)->for($this->owner->refresh())?->organisation->id)->toBe($tenant->id)
        ->and(AuditEvent::query()->where('action', 'tenant.signed_up')->count())->toBe(1);

    // Once only, even when the webhook confirms the same order again.
    app(Checkout::class)->confirm($order->refresh());
    expect(Organisation::query()->where('name', 'Bluebonnet Realty')->count())->toBe(1);
});

it('sells only the plans for that kind of tenant', function () {
    $this->actingAs($this->owner)->post('/start/brokerage', ['name' => 'Bluebonnet Realty', 'states' => ['TX'], 'price_id' => $this->school->id])
        ->assertSessionHasErrors('price');
});

it('leaves associations to the REOS team', function () {
    $this->actingAs($this->owner)->get('/start/association')->assertNotFound();
});

it('ignores a sign-up that belongs to someone else', function () {
    $this->actingAs($this->owner)->post('/start/school', ['name' => 'Gulf Coast School of Real Estate', 'states' => ['TX'], 'price_id' => $this->school->id]);
    $signup = TenantSignup::query()->sole();
    $other = User::factory()->create();

    $started = $this->actingAs($other)->postJson("/checkout/{$this->school->id}", ['signup' => $signup->uuid])->assertCreated();

    expect($signup->refresh()->order_id)->toBeNull()
        ->and(Order::query()->where('uuid', $started->json('order'))->sole()->user_id)->toBe($other->id);
});

it('needs an account first', function () {
    $this->get('/start/brokerage')->assertRedirect('/login');
});
