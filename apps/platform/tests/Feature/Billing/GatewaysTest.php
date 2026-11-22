<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Gateways\AuthorizeNetGateway;
use App\Billing\OrderStatus;
use App\Enums\OrganisationType;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\GatewaySetting;
use App\Models\IntegrationSetting;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\User;
use Database\Seeders\Demo\DemoBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School', 'slug' => 'texas-realty-school']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Sofia Reyes']);
    app(DemoBilling::class)->seed();
    $this->complete = Price::query()->whereHas('product', fn ($q) => $q->where('key', 'texas-pre-licence-complete'))->sole();
    $this->monthly = Price::query()->whereHas('product', fn ($q) => $q->where('key', 'texas-pre-licence-monthly'))->sole();
});

/** Screen 66: PayPal serves Texas packages and is the default. */
function paypalForTexasPackages(object $test): void
{
    $test->actingAs($test->ops)->put('/admin/gateways/paypal', [
        'states' => ['TX'], 'used_for' => ['package'], 'is_default' => true, 'tenant_accounts' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();
}

function sandboxKeys(string $gateway, array $keys): void
{
    app(CredentialVault::class)->put($gateway, CredentialEnvironment::Sandbox, $keys, null);
    IntegrationSetting::query()->updateOrCreate(['integration_id' => $gateway], ['mode' => IntegrationMode::Sandbox]);
}

describe('payment gateways (screen 66)', function () {
    it('lists every gateway with status, routing, payouts, tenant accounts and health', function () {
        $this->actingAs($this->ops)->get('/admin/gateways')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/gateways/index')
            ->has('gateways', 3)
            ->where('gateways.0.id', 'stripe')
            ->where('gateways.0.is_default', true)
            ->where('gateways.0.capabilities.payouts', true)
            ->where('gateways.1.id', 'paypal')
            ->where('gateways.1.capabilities.recurring', false)
            ->where('gateways.2.id', 'authorize-net')
            ->where('fees.platform_fee_percent', 10)
            ->where('fees.refund_days', 7));
    });

    it('saves how a gateway is used, keeps one default, and audits before and after', function () {
        paypalForTexasPackages($this);

        expect(GatewaySetting::query()->find('paypal'))
            ->states->toBe(['TX'])
            ->used_for->toBe(['package'])
            ->is_default->toBeTrue();

        $this->put('/admin/gateways/stripe', ['states' => [], 'used_for' => [], 'is_default' => true, 'tenant_accounts' => true])->assertSessionHasNoErrors();
        expect(GatewaySetting::query()->find('paypal')->is_default)->toBeFalse()
            ->and(GatewaySetting::query()->find('stripe')->tenant_accounts)->toBeTrue();

        $event = AuditEvent::query()->where('action', 'gateway.updated')->where('subject_id', 'paypal')->sole();
        expect($event->properties['before']['states'])->toBeNull()
            ->and($event->properties['after']['states'])->toBe(['TX']);
    });

    it('allows tenant-owned accounts only where tenants can connect their own (Stripe, Authorize.net)', function () {
        $this->actingAs($this->ops)->put('/admin/gateways/paypal', ['states' => [], 'used_for' => [], 'is_default' => false, 'tenant_accounts' => true]);

        $this->put('/admin/gateways/authorize-net', ['states' => [], 'used_for' => [], 'is_default' => false, 'tenant_accounts' => true]);

        expect(GatewaySetting::query()->find('paypal')->tenant_accounts)->toBeFalse()
            ->and(GatewaySetting::query()->find('authorize-net')->tenant_accounts)->toBeTrue();
    });

    it('refuses unknown states, kinds and gateways', function () {
        $this->actingAs($this->ops)->put('/admin/gateways/paypal', ['states' => ['ZZ'], 'used_for' => ['lunch'], 'is_default' => false, 'tenant_accounts' => false])
            ->assertSessionHasErrors(['states.0', 'used_for.0']);
        $this->put('/admin/gateways/razorpay', ['states' => [], 'used_for' => [], 'is_default' => false, 'tenant_accounts' => false])->assertNotFound();
    });

    it('is for the super admin only', function () {
        $this->actingAs($this->sofia)->get('/admin/gateways')->assertForbidden();
        $this->post('/admin/gateways/stripe/pause', ['reason' => 'Testing'])->assertForbidden();
    });
});

describe('routing checkout', function () {
    it('sends Texas packages to PayPal, asks for the ZIP code and charges the same tax', function () {
        paypalForTexasPackages($this);

        $this->actingAs($this->sofia)->get("/checkout/{$this->complete->id}")->assertInertia(fn (Assert $page) => $page
            ->where('gateway.id', 'paypal')
            ->where('gateway.simulator', true)
            ->where('gateway.needs_postal_code', true));

        $this->postJson("/checkout/{$this->complete->id}")->assertUnprocessable()->assertJsonValidationErrors('postal_code');
        $this->postJson("/checkout/{$this->complete->id}", ['postal_code' => '7870'])->assertUnprocessable();

        $started = $this->postJson("/checkout/{$this->complete->id}", ['postal_code' => '78701'])->assertCreated()
            ->assertJson(['gateway' => 'paypal', 'simulator' => true]);

        $order = Order::query()->where('uuid', $started->json('order'))->sole();
        $tax = (int) round($order->chargeCents() * 0.0825);
        expect($order->gateway)->toBe('paypal')->and($order->postal_code)->toBe('78701')->and($order->tax_cents)->toBe($tax)
            ->and($started->json('total'))->toBe($order->chargeCents() + $tax);

        $this->post("/checkout/orders/{$order->uuid}/simulate")->assertRedirect("/checkout/orders/{$order->uuid}/return");
        $this->get("/checkout/orders/{$order->uuid}/return")->assertOk();
        expect($order->refresh())
            ->status->toBe(OrderStatus::Paid)
            ->tax_cents->toBe($tax)
            ->total_cents->toBe($order->chargeCents() + $tax);
    });

    it('keeps plans that renew on Stripe, which can take them', function () {
        $this->actingAs($this->ops)->put('/admin/gateways/paypal', ['states' => [], 'used_for' => [], 'is_default' => true, 'tenant_accounts' => false]);

        $this->actingAs($this->sofia)->get("/checkout/{$this->monthly->id}")->assertInertia(fn (Assert $page) => $page
            ->where('gateway.id', 'stripe')
            ->where('gateway.needs_postal_code', false));
        $this->get("/checkout/{$this->complete->id}")->assertInertia(fn (Assert $page) => $page->where('gateway.id', 'paypal'));
    });

    it('kill switch: checkout moves to the next gateway, or says purchases are paused', function () {
        paypalForTexasPackages($this);
        $this->post('/admin/gateways/paypal/pause', ['reason' => 'PayPal outage'])->assertRedirect();

        $this->actingAs($this->sofia)->get("/checkout/{$this->complete->id}")->assertInertia(fn (Assert $page) => $page->where('gateway.id', 'stripe'));

        $this->actingAs($this->ops)->post('/admin/gateways/stripe/pause', ['reason' => 'Card testing attack'])->assertRedirect();
        $this->actingAs($this->sofia)->get("/checkout/{$this->complete->id}")->assertInertia(fn (Assert $page) => $page->where('gateway', null));
        $this->postJson("/checkout/{$this->complete->id}")->assertUnprocessable()->assertJsonValidationErrors('price');

        expect(AuditEvent::query()->where('action', 'gateway.paused')->where('subject_id', 'stripe')->sole()->properties)
            ->toMatchArray(['from' => 'running', 'to' => 'paused', 'reason' => 'Card testing attack']);

        $this->actingAs($this->ops)->delete('/admin/gateways/stripe/pause')->assertRedirect();
        expect(GatewaySetting::query()->find('stripe')->paused)->toBeFalse()
            ->and(AuditEvent::query()->where('action', 'gateway.resumed')->count())->toBe(1);
    });

    it('needs a reason to pause', function () {
        $this->actingAs($this->ops)->post('/admin/gateways/stripe/pause', ['reason' => ''])->assertSessionHasErrors('reason');
    });
});

describe('PayPal (sandbox)', function () {
    it('works out tax with Stripe Tax, sends the buyer to PayPal, captures on return and records the tax', function () {
        sandboxKeys('stripe', ['publishable_key' => 'pk_test_reos', 'secret_key' => 'sk_test_reos', 'webhook_secret' => 'whsec_reos']);
        sandboxKeys('paypal', ['client_id' => 'pp_client', 'client_secret' => 'pp_secret', 'webhook_id' => 'WH-1']);
        paypalForTexasPackages($this);

        Http::fake([
            'api.stripe.com/v1/tax/calculations' => Http::response(['id' => 'taxcalc_1', 'tax_amount_exclusive' => 3300]),
            'api.stripe.com/v1/tax/transactions/create_from_calculation' => Http::response(['id' => 'tax_txn_1']),
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21']),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => '5O190127TN364715T', 'status' => 'PAYER_ACTION_REQUIRED', 'links' => [
                ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T'],
            ]], 201),
            'api-m.sandbox.paypal.com/v2/checkout/orders/5O190127TN364715T' => Http::response(['id' => '5O190127TN364715T', 'status' => 'APPROVED']),
            'api-m.sandbox.paypal.com/v2/checkout/orders/5O190127TN364715T/capture' => Http::response(['id' => '5O190127TN364715T', 'status' => 'COMPLETED', 'purchase_units' => [
                ['payments' => ['captures' => [['id' => '3C679366HH908993F', 'status' => 'COMPLETED', 'amount' => ['value' => '433.00', 'currency_code' => 'USD']]]]],
            ]]),
        ]);

        $started = $this->actingAs($this->sofia)->postJson("/checkout/{$this->complete->id}", ['postal_code' => '78701'])->assertCreated()
            ->assertJsonPath('redirect.method', 'GET')
            ->assertJsonPath('redirect.url', 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T')
            ->assertJsonPath('tax', 3300);

        $order = Order::query()->where('uuid', $started->json('order'))->sole();
        expect($order->tax_calculation_id)->toBe('taxcalc_1')->and($order->gateway_session_id)->toBe('5O190127TN364715T');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/v2/checkout/orders') && $r->method() === 'POST' && ! str_contains($r->url(), 'capture')
            && $r['purchase_units'][0]['amount']['breakdown']['tax_total']['value'] === '33.00'
            && ! str_contains($r['payment_source']['paypal']['experience_context']['return_url'], 'CHECKOUT_SESSION_ID'));

        $this->get("/checkout/orders/{$order->uuid}/return")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('order.status', 'paid')
            ->where('order.gateway', 'paypal'));

        expect($order->refresh())->status->toBe(OrderStatus::Paid)->gateway_payment_id->toBe('3C679366HH908993F')->total_cents->toBe(43300);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'create_from_calculation') && $r['calculation'] === 'taxcalc_1');
    });
});

describe('Authorize.net (sandbox)', function () {
    beforeEach(function () {
        sandboxKeys('authorize-net', ['api_login_id' => 'anet_login', 'transaction_key' => 'anet_txn', 'signature_key' => 'ABCDEF0123']);
        $this->actingAs($this->ops)->put('/admin/gateways/authorize-net', ['states' => ['TX'], 'used_for' => ['package'], 'is_default' => true, 'tenant_accounts' => false]);
    });

    it('posts the buyer to the Accept Hosted page with its token (card data never touches REOS)', function () {
        Http::fake(['apitest.authorize.net/*' => Http::response("\xEF\xBB\xBF".json_encode(['token' => 'tok_123', 'messages' => ['resultCode' => 'Ok']]))]);

        $this->actingAs($this->sofia)->postJson("/checkout/{$this->complete->id}", ['postal_code' => '78701'])->assertCreated()
            ->assertJsonPath('gateway', 'authorize-net')
            ->assertJsonPath('redirect.method', 'POST')
            ->assertJsonPath('redirect.url', 'https://test.authorize.net/payment/payment')
            ->assertJsonPath('redirect.fields.token', 'tok_123');
    });

    it('accepts signed webhooks once and refuses unsigned ones', function () {
        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $this->sofia->id, 'product_id' => $this->complete->product_id, 'price_id' => $this->complete->id,
            'state_code' => 'TX', 'amount_cents' => 40000, 'discount_cents' => 0, 'currency' => 'usd', 'status' => OrderStatus::Pending,
            'gateway' => 'authorize-net', 'environment' => 'sandbox', 'gateway_session_id' => 'R2612220000000001',
        ]);
        Http::fake(['apitest.authorize.net/*' => Http::response(json_encode(['messages' => ['resultCode' => 'Ok'], 'transactions' => [
            ['transId' => '60123', 'invoiceNumber' => 'R2612220000000001', 'transactionStatus' => 'capturedPendingSettlement', 'settleAmount' => 433.0],
        ]]))]);

        $body = json_encode(['notificationId' => 'n-1', 'eventType' => 'net.authorize.payment.authcapture.created', 'payload' => ['id' => '60123', 'invoiceNumber' => 'R2612220000000001']]);
        $sign = fn (string $key) => 'sha512='.hash_hmac('sha512', $body, $key);

        $this->call('POST', '/webhooks/authorize-net', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_ANET_SIGNATURE' => $sign('WRONG')], $body)->assertStatus(400);
        $this->call('POST', '/webhooks/authorize-net', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_ANET_SIGNATURE' => $sign('ABCDEF0123')], $body)->assertOk();
        $this->call('POST', '/webhooks/authorize-net', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_ANET_SIGNATURE' => $sign('ABCDEF0123')], $body)->assertOk();

        expect($order->refresh())->status->toBe(OrderStatus::Paid)->gateway_payment_id->toBe('60123');
    });

    it('keeps invoice numbers within 20 characters', function () {
        $order = new Order(['uuid' => (string) Str::uuid()]);

        expect(strlen(AuthorizeNetGateway::invoice($order)))->toBeLessThanOrEqual(20);
    });
});
