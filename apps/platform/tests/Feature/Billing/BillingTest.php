<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Checkout;
use App\Billing\OrderStatus;
use App\Billing\SubscriptionStatus;
use App\Enums\OrganisationType;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\Entitlement;
use App\Models\IntegrationSetting;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\Product;
use App\Models\SponsorDiscount;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentFailed;
use App\Notifications\PurchaseReceipt;
use Database\Seeders\Demo\DemoBilling;
use Database\Seeders\Demo\DemoContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School', 'slug' => 'texas-realty-school']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Sofia Reyes']);
    app(DemoBilling::class)->seed();
    $this->complete = Price::query()->whereHas('product', fn ($q) => $q->where('key', 'texas-pre-licence-complete'))->sole();
    $this->monthly = Price::query()->whereHas('product', fn ($q) => $q->where('key', 'texas-pre-licence-monthly'))->sole();
});

function stripeSandbox(): void
{
    app(CredentialVault::class)->put('stripe', CredentialEnvironment::Sandbox, [
        'publishable_key' => 'pk_test_reos', 'secret_key' => 'sk_test_reos', 'webhook_secret' => 'whsec_reos',
    ], null);
    IntegrationSetting::query()->updateOrCreate(['integration_id' => 'stripe'], ['mode' => IntegrationMode::Sandbox]);
}

function stripeEvent(object $test, array $event): TestResponse
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $time = time();

    return $test->call('POST', '/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', $time.'.'.$body, 'whsec_reos'),
    ], $body);
}

describe('plans and pricing (screen 67)', function () {
    it('lists plans by buyer, one row per priced state', function () {
        $this->actingAs($this->ops)->get('/admin/plans?tab=candidates')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/plans/index')
            ->has('products', 4)
            ->where('products.0.prices.0.amount', fn ($amount) => is_int($amount)));
    });

    it('keeps the price history when a price changes', function () {
        $product = Product::query()->where('key', 'texas-exam-prep')->sole();

        $this->actingAs($this->ops)->post("/admin/plans/{$product->id}/prices", ['state' => 'tx', 'interval' => 'once', 'amount' => '139.00'])->assertRedirect();

        $prices = Price::query()->where('product_id', $product->id)->orderBy('id')->get();
        expect($prices)->toHaveCount(2)
            ->and($prices[0]->ends_at)->not->toBeNull()
            ->and($prices[1]->amount_cents)->toBe(13900)
            ->and($prices[1]->isCurrent())->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'price.changed')->sole()->properties)->toMatchArray(['from' => 12900, 'to' => 13900]);
    });

    it('creates a plan as a draft and only lets it go live with a price', function () {
        $this->actingAs($this->ops)->post('/admin/plans', [
            'name' => 'Broker Compliance', 'kind' => 'plan', 'buyer' => 'broker',
            'includes' => "CE compliance for every agent\nPolicies", 'features' => "broker.compliance\nagent.core",
        ])->assertRedirect('/admin/plans?tab=agents');

        $product = Product::query()->where('key', 'broker-compliance')->sole();
        expect($product->includes)->toBe(['CE compliance for every agent', 'Policies'])->and($product->features)->toBe(['broker.compliance', 'agent.core']);

        $this->from('/admin/plans')->put("/admin/plans/{$product->id}/status", ['status' => 'live'])->assertSessionHasErrors('status');
        $this->post("/admin/plans/{$product->id}/prices", ['state' => 'TX', 'interval' => 'month', 'amount' => 99]);
        $this->put("/admin/plans/{$product->id}/status", ['status' => 'live'])->assertSessionHasNoErrors();
    });

    it('refuses feature keys that are not keys, and a floor above the price', function () {
        $product = Product::query()->where('key', 'texas-exam-prep')->sole();

        $this->actingAs($this->ops)->patch("/admin/plans/{$product->id}", ['features' => ['Learn everything']])->assertSessionHasErrors('features.0');
        $this->post("/admin/plans/{$product->id}/prices", ['state' => 'TX', 'interval' => 'once', 'amount' => 100, 'floor' => 120])->assertSessionHasErrors('floor');
    });

    it('is for the super admin only', function () {
        $this->actingAs($this->sofia)->get('/admin/plans')->assertForbidden();
    });
});

describe('checkout on the Simulator (screen 3)', function () {
    it('done when: a purchase on the web unlocks the app within seconds', function () {
        $this->actingAs($this->sofia)->getJson('/api/v1/me/entitlements')->assertJson(['active' => false, 'features' => []]);

        $this->get("/checkout/{$this->complete->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('billing/checkout')
            ->where('quote.amount', 49900)
            ->where('quote.discount', 10000)
            ->where('quote.sponsor', 'Texas Realty School')
            ->where('quote.charge', 39900)
            ->where('gateway.simulator', true));

        $started = $this->postJson("/checkout/{$this->complete->id}")->assertCreated()->assertJson(['simulator' => true])->json();
        $this->post("/checkout/orders/{$started['order']}/simulate")->assertRedirect("/checkout/orders/{$started['order']}/return");

        $this->get("/checkout/orders/{$started['order']}/return")->assertInertia(fn (Assert $page) => $page
            ->component('billing/complete')
            ->where('order.status', 'paid')
            ->where('order.total', 39900 + (int) round(39900 * 0.0825))
            ->where('unlocked', ['learn.qualifying', 'learn.test_prep']));

        // The app asks again when it comes back to the front.
        $this->getJson('/api/v1/me/entitlements')->assertJson(['active' => true, 'state' => 'TX', 'features' => ['learn.qualifying', 'learn.test_prep']]);
        Notification::assertSentTo($this->sofia, PurchaseReceipt::class);
    });

    it('never goes below the floor REOS set', function () {
        SponsorDiscount::query()->update(['amount_cents' => 30000]);

        expect(app(Checkout::class)->quote($this->sofia, $this->complete))->toMatchArray(['discount' => 20000, 'charge' => 29900]);
    });

    it('pauses purchases when the gateway is off', function () {
        IntegrationSetting::query()->create(['integration_id' => 'stripe', 'mode' => IntegrationMode::Off]);

        $this->actingAs($this->sofia)->postJson("/checkout/{$this->complete->id}")->assertUnprocessable()->assertJsonValidationErrors('price');
    });

    it('does not sell draft plans or ended prices', function () {
        $draft = Product::query()->where('key', 'louisiana-pre-licence')->sole();
        $price = Price::query()->create(['product_id' => $draft->id, 'state_code' => 'LA', 'amount_cents' => 100, 'interval' => 'once', 'starts_at' => now()]);

        $this->actingAs($this->sofia)->get("/checkout/{$price->id}")->assertNotFound();
    });

    it('shows Plan and billing with the plans for my role and state, and my receipts', function () {
        $this->actingAs($this->sofia)->get('/settings/billing')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/billing')
            ->has('plans', 3)
            ->where('plans.2.sponsor', 'Texas Realty School')
            ->where('access', []));
    });

    it('opens the rest of a course only with its plan', function () {
        app(DemoContent::class)->seed();
        $second = Lesson::query()->where('title', '6.3 Intermediary and appointed agents')->sole();

        $this->actingAs($this->sofia)->get("/learn/lessons/{$second->id}")->assertInertia(fn (Assert $page) => $page->component('learn/locked'));
        $this->getJson("/api/v1/learn/lessons/{$second->id}")->assertStatus(402)->assertJson(['membership_required' => true]);
        $principles = collect($this->getJson('/api/v1/learn/courses')->json('data'))->firstWhere('title', 'Principles of Real Estate II');
        expect($principles['unlocked'])->toBeFalse()
            ->and($principles['lessons'][0]['locked'])->toBeFalse()
            ->and($principles['lessons'][1]['locked'])->toBeTrue();

        $started = $this->postJson("/checkout/{$this->complete->id}")->json();
        $this->post("/checkout/orders/{$started['order']}/simulate");
        $this->get("/checkout/orders/{$started['order']}/return");

        $this->getJson("/api/v1/learn/lessons/{$second->id}")->assertOk();
    });
});

describe('Stripe (B5.1)', function () {
    beforeEach(fn () => stripeSandbox());

    it('starts embedded Checkout with Stripe Tax and the sponsor discount, then confirms it directly', function () {
        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_sofia']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'client_secret' => 'cs_test_1_secret']),
            'api.stripe.com/v1/checkout/sessions/cs_test_1*' => Http::response([
                'id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 43192,
                'total_details' => ['amount_tax' => 3292],
                'payment_intent' => ['id' => 'pi_1', 'latest_charge' => ['receipt_url' => 'https://pay.stripe.com/receipts/1']],
            ]),
        ]);

        $started = $this->actingAs($this->sofia)->postJson("/checkout/{$this->complete->id}")->assertCreated()
            ->assertJson(['client_secret' => 'cs_test_1_secret', 'simulator' => false])->json();

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request['ui_mode'] === 'embedded'
            && $request['mode'] === 'payment'
            && $request['customer'] === 'cus_sofia'
            && $request['automatic_tax']['enabled'] === 'true'
            && (int) $request['line_items'][0]['price_data']['unit_amount'] === 39900
            && $request['line_items'][0]['price_data']['tax_behavior'] === 'exclusive'
            && $request['client_reference_id'] === $started['order']
            && $request->hasHeader('Idempotency-Key', 'checkout:'.$started['order'])
            && $request->hasHeader('Authorization', 'Bearer sk_test_reos'));

        $this->get("/checkout/orders/{$started['order']}/return?session_id=cs_test_1")->assertInertia(fn (Assert $page) => $page
            ->where('order.status', 'paid')
            ->where('order.tax', 3292)
            ->where('order.total', 43192)
            ->where('order.receipt_url', 'https://pay.stripe.com/receipts/1'));

        // Twice (the return and the webhook together): unlocked once, one receipt.
        app(Checkout::class)->confirm(Order::query()->sole());
        expect(Entitlement::query()->count())->toBe(2);
        Notification::assertSentToTimes($this->sofia, PurchaseReceipt::class, 1);
    });

    it('handles signed webhooks once, and refuses unsigned ones', function () {
        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_sofia']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_2', 'client_secret' => 's']),
            'api.stripe.com/v1/checkout/sessions/cs_test_2*' => Http::response(['id' => 'cs_test_2', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 39900, 'total_details' => ['amount_tax' => 0], 'payment_intent' => 'pi_2']),
        ]);
        $this->actingAs($this->sofia)->postJson("/checkout/{$this->complete->id}");

        $this->postJson('/webhooks/stripe', ['id' => 'evt_x', 'type' => 'checkout.session.completed'])->assertStatus(400);
        stripeEvent($this, ['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_2']]])->assertOk()->assertJson(['received' => true]);
        stripeEvent($this, ['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_2']]])->assertJson(['duplicate' => true]);

        expect(Order::query()->sole()->status)->toBe(OrderStatus::Paid);

        // Refunded in full at Stripe: access ends at once.
        stripeEvent($this, ['id' => 'evt_2', 'type' => 'charge.refunded', 'data' => ['object' => ['refunded' => true, 'payment_intent' => 'pi_2']]]);
        expect(Order::query()->sole()->status)->toBe(OrderStatus::Refunded)
            ->and(Entitlement::query()->active()->count())->toBe(0);
    });

    it('renews, gives a 7-day grace period after a failed payment, and ends on cancel', function () {
        $periodEnd = now()->addMonth()->getTimestamp();
        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_sofia']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_sub', 'client_secret' => 's']),
            'api.stripe.com/v1/checkout/sessions/cs_sub*' => Http::response(['id' => 'cs_sub', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 7900, 'subscription' => ['id' => 'sub_1', 'items' => ['data' => [['current_period_end' => $periodEnd]]]], 'invoice' => ['hosted_invoice_url' => 'https://invoice.stripe.com/i/1']]),
        ]);
        $started = $this->actingAs($this->sofia)->postJson("/checkout/{$this->monthly->id}")->json();
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.stripe.com/v1/checkout/sessions' && $r['mode'] === 'subscription' && $r['line_items'][0]['price_data']['recurring']['interval'] === 'month');
        $this->get("/checkout/orders/{$started['order']}/return");

        $subscription = Subscription::query()->sole();
        expect($subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(Entitlement::query()->first()->ends_at->getTimestamp())->toBe($periodEnd + 2 * 86400);

        stripeEvent($this, ['id' => 'evt_f', 'type' => 'invoice.payment_failed', 'data' => ['object' => ['subscription' => 'sub_1']]]);
        expect($subscription->refresh()->status)->toBe(SubscriptionStatus::PastDue)
            ->and($subscription->grace_ends_at->isSameDay(now()->addDays(7)))->toBeTrue();
        Notification::assertSentTo($this->sofia, PaymentFailed::class);

        $next = now()->addMonths(2)->getTimestamp();
        stripeEvent($this, ['id' => 'evt_p', 'type' => 'invoice.paid', 'data' => ['object' => ['parent' => ['subscription_details' => ['subscription' => 'sub_1']], 'lines' => ['data' => [['period' => ['end' => $next]]]]]]]);
        expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)->and($subscription->grace_ends_at)->toBeNull();

        stripeEvent($this, ['id' => 'evt_c', 'type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_1']]]);
        expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Canceled)
            ->and(Entitlement::query()->active()->count())->toBe(0);
    });

    it('finds payments whose webhook never came', function () {
        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_sofia']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_lost', 'client_secret' => 's']),
            'api.stripe.com/v1/checkout/sessions/cs_lost*' => Http::response(['id' => 'cs_lost', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 39900]),
        ]);
        $this->actingAs($this->sofia)->postJson("/checkout/{$this->complete->id}");
        $this->travel(6)->minutes();

        $this->artisan('reos:billing:reconcile')->assertSuccessful();

        expect(Order::query()->sole()->status)->toBe(OrderStatus::Paid);
    });
});
