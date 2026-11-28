<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Brokerage\BrokerageSetup;
use App\Models\AuditEvent;
use App\Models\BrokerVerification;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\TenantSignup;
use App\Models\User;
use App\Services\Authorization\Memberships;
use Database\Seeders\Demo\DemoTenantPlans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * TREC-verified brokerage setup (A15.1a, ADR 0069): only the designated
 * broker on TREC's records creates a brokerage, once.
 */

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-11-28 09:00:00');
    app(DemoTenantPlans::class)->seed();
    // TREC's records (Simulator): Grace Hill (broker 9033333) is the designated broker of
    // Bayou City Homes LLC (0600123), which sponsors Maria Lopez and Omar Haddad.
    $this->artisan('reos:regulators:import')->assertSuccessful();
    $this->grace = User::factory()->create(['name' => 'Grace Hill']);
});

describe('verify your brokerage (A15.1a)', function () {
    it('finds the brokerages a broker is designated broker of, with their sponsored agents', function () {
        $found = $this->actingAs($this->grace)->postJson('/api/v1/brokerage/verify/lookup', ['licence' => '9033333'])->assertOk()->json('data');

        expect($found['licence'])->toMatchArray(['number' => '9033333', 'name' => 'Grace Hill', 'active' => true])
            ->and($found['name_matches'])->toBeTrue()
            ->and($found['brokerages'])->toBe([
                ['number' => '0600123', 'name' => 'Bayou City Homes LLC', 'kind' => 'entity', 'sponsored' => 2, 'on_reos' => false],
                ['number' => '9033333', 'name' => 'Grace Hill', 'kind' => 'individual', 'sponsored' => 0, 'on_reos' => false],
            ]);
    });

    it('sets up a verified brokerage and shows the setup checklist (the done-when)', function () {
        $verified = $this->actingAs($this->grace)->postJson('/api/v1/brokerage/verify/confirm', ['licence' => '9033333', 'brokerage' => '0600123', 'role' => 'designated_broker'])->assertOk()->json('data');
        expect($verified)->toMatchArray(['role' => 'designated_broker', 'brokerage' => 'Bayou City Homes LLC', 'sponsored' => 2])
            ->and(AuditEvent::query()->where('action', 'brokerage.verified')->count())->toBe(1);

        $price = Price::query()->whereHas('product', fn ($q) => $q->where('key', 'brokerage-starter'))->sole();
        $this->post('/start/brokerage', ['name' => 'Bayou City Homes', 'states' => ['TX'], 'price_id' => $price->id, 'verification' => $verified['verification']])->assertRedirect();
        $signup = TenantSignup::query()->sole();
        $order = Order::query()->where('uuid', $this->postJson("/checkout/{$price->id}", ['signup' => $signup->uuid])->json('order'))->sole();
        $this->post("/checkout/orders/{$order->uuid}/simulate");
        $this->get("/checkout/orders/{$order->uuid}/return")->assertOk();

        $tenant = Organisation::query()->where('trec_number', '0600123')->sole();
        expect(BrokerVerification::query()->sole()->used_at)->not->toBeNull();

        $setup = $this->actingAs($this->grace->refresh())->getJson('/api/v1/organisation/setup')->assertOk()->json('data');
        expect($setup['trec_number'])->toBe('0600123')->and($setup['total'])->toBe(6)
            ->and(collect($setup['steps'])->firstWhere('key', 'verify')['done'])->toBeTrue()
            ->and(collect($setup['steps'])->firstWhere('key', 'agents')['done'])->toBeFalse();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('brokerSetup.trec_number', '0600123')->where('brokerSetup.done', 1));

        // The brokerage is on REOS now: nobody can claim it again.
        $this->postJson('/api/v1/brokerage/verify/lookup', ['licence' => '9033333'])->assertJsonPath('data.brokerages.0.on_reos', true);
        $this->postJson('/api/v1/brokerage/verify/confirm', ['licence' => '9033333', 'brokerage' => '0600123', 'role' => 'designated_broker'])->assertUnprocessable()->assertJsonValidationErrors('brokerage');
        expect($tenant->name)->toBe('Bayou City Homes');
    });

    it('refuses an agent license, another person\'s license, and someone else\'s brokerage', function () {
        $this->actingAs($this->grace)->postJson('/api/v1/brokerage/verify/lookup', ['licence' => '0733333'])->assertUnprocessable()->assertJsonValidationErrors('licence');
        $this->postJson('/api/v1/brokerage/verify/lookup', ['licence' => '1234567'])->assertJsonValidationErrors('licence');

        $sam = User::factory()->create(['name' => 'Sam Ortiz']);
        $this->actingAs($sam)->postJson('/api/v1/brokerage/verify/lookup', ['licence' => '9033333'])->assertOk()->assertJsonPath('data.name_matches', false);
        $this->postJson('/api/v1/brokerage/verify/confirm', ['licence' => '9033333', 'brokerage' => '0600123', 'role' => 'designated_broker'])->assertJsonValidationErrors('licence');

        // Linda Park (broker 9012345) isn't Bayou City Homes' designated broker.
        $linda = User::factory()->create(['name' => 'Linda Park']);
        $this->actingAs($linda)->postJson('/api/v1/brokerage/verify/confirm', ['licence' => '9012345', 'brokerage' => '0600123', 'role' => 'designated_broker'])->assertJsonValidationErrors('brokerage');
        expect(BrokerVerification::query()->count())->toBe(0);
    });

    it('sends office admins and team leaders to their designated broker for an invite', function () {
        $this->actingAs($this->grace)->postJson('/api/v1/brokerage/verify/confirm', ['licence' => '9033333', 'brokerage' => '0600123', 'role' => 'office_admin'])
            ->assertOk()->assertJsonPath('data', ['verification' => null, 'role' => 'office_admin', 'brokerage' => null, 'sponsored' => null]);
        expect(BrokerVerification::query()->count())->toBe(0);
    });

    it('shows the checklist only to the brokerage\'s brokers', function () {
        $agent = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($agent)->getJson('/api/v1/organisation/setup')->assertNotFound();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('brokerSetup', null));

        $broker = User::factory()->create();
        $brokerage = Organisation::factory()->create();
        app(Memberships::class)->grant($broker, Role::Broker, $brokerage, $broker);
        expect(app(BrokerageSetup::class)->for($brokerage))->toMatchArray(['trec_number' => null, 'done' => 0]);
    });
});
