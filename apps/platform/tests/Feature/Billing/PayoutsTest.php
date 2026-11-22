<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Checkout;
use App\Billing\OrderStatus;
use App\Billing\Payouts\PayeeRole;
use App\Billing\Payouts\PayoutLineStatus;
use App\Billing\Payouts\PayoutRun;
use App\Enums\OrganisationType;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\ConnectedAccount;
use App\Models\GatewaySetting;
use App\Models\IntegrationSetting;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Payout;
use App\Models\PayoutLine;
use App\Models\Price;
use App\Models\User;
use Database\Seeders\Demo\DemoBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School', 'slug' => 'texas-realty-school']);
    $this->paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
    $this->rachel = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Dr. Rachel Hughes', 'email' => 'rachel.hughes@demo.reos.test']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Sofia Reyes']);
    app(DemoBilling::class)->seed();
    $this->complete = Price::query()->whereHas('product', fn ($q) => $q->where('key', 'texas-pre-licence-complete'))->sole();
    $this->product = $this->complete->product;
});

/** Screen 67: Texas Realty School sells the package; Dr. Hughes gets 15% of what's left after the fee. */
function schoolSells(object $test): void
{
    $test->actingAs($test->ops)->patch("/admin/plans/{$test->product->id}", ['seller_organisation_id' => $test->school->id])->assertSessionHasNoErrors();
    $test->put("/admin/plans/{$test->product->id}/splits", ['splits' => [['email' => 'rachel.hughes@demo.reos.test', 'role' => 'instructor', 'percent' => 15]]])->assertSessionHasNoErrors();
}

/** Sofia buys the package on the Simulator ($499 − $100 sponsor discount). */
function sofiaBuys(object $test): Order
{
    $started = $test->actingAs($test->sofia)->postJson("/checkout/{$test->complete->id}")->assertCreated();
    $order = Order::query()->where('uuid', $started->json('order'))->sole();
    $test->post("/checkout/orders/{$order->uuid}/simulate");
    $test->get("/checkout/orders/{$order->uuid}/return")->assertOk();

    return $order->refresh();
}

function readyAccount(string $type, int $id, string $acct = 'sim_acct_ready'): ConnectedAccount
{
    return ConnectedAccount::query()->create([
        'owner_type' => $type, 'owner_id' => $id, 'environment' => 'simulator', 'kind' => 'express',
        'stripe_account_id' => $acct, 'details_submitted' => true, 'payouts_enabled' => true,
    ]);
}

describe('revenue split', function () {
    it('pays the school what is left after the platform fee and the instructor her share', function () {
        schoolSells($this);
        $order = sofiaBuys($this);

        expect($order->status)->toBe(OrderStatus::Paid);
        $lines = PayoutLine::query()->where('order_id', $order->id)->get()->keyBy('payee_type');

        // $399 collected (tax not shared): fee $39.90; rest $359.10; 15% = $53.86; school $305.24.
        expect($lines['organisation'])->payee_id->toBe($this->school->id)->platform_fee_cents->toBe(3990)->amount_cents->toBe(30524)->role->toBe(PayeeRole::Seller)
            ->and($lines['user'])->payee_id->toBe($this->rachel->id)->amount_cents->toBe(5386)->role->toBe(PayeeRole::Instructor)
            ->and($lines['organisation']->amount_cents + $lines['user']->amount_cents + $lines['organisation']->platform_fee_cents)->toBe(39900);
    });

    it('earns no one anything on products REOS sells', function () {
        $order = sofiaBuys($this);

        expect(PayoutLine::query()->where('order_id', $order->id)->count())->toBe(0);
    });

    it('cancels shares that were not paid out when the order is refunded', function () {
        schoolSells($this);
        $order = sofiaBuys($this);
        app(Checkout::class)->refunded((string) $order->gateway_payment_id);

        expect(PayoutLine::query()->where('order_id', $order->id)->pluck('status')->unique()->all())->toBe([PayoutLineStatus::Cancelled]);
    });

    it('keeps revenue splits to 100% and to products a school sells, and audits them', function () {
        $this->actingAs($this->ops)->put("/admin/plans/{$this->product->id}/splits", ['splits' => [['email' => 'rachel.hughes@demo.reos.test', 'role' => 'instructor', 'percent' => 15]]])
            ->assertSessionHasErrors('splits');

        schoolSells($this);
        $this->put("/admin/plans/{$this->product->id}/splits", ['splits' => [
            ['email' => 'rachel.hughes@demo.reos.test', 'role' => 'instructor', 'percent' => 60],
            ['email' => $this->sofia->email, 'role' => 'mentor', 'percent' => 50],
        ]])->assertSessionHasErrors('splits');
        $this->put("/admin/plans/{$this->product->id}/splits", ['splits' => [['email' => 'nobody@example.com', 'role' => 'instructor', 'percent' => 5]]])
            ->assertSessionHasErrors('splits.0.email');

        expect(AuditEvent::query()->where('action', 'product.splits_changed')->sole()->properties['after'])->toBe([['user_id' => $this->rachel->id, 'role' => 'instructor', 'percent' => 15]]);
    });

    it('only lets a school sell', function () {
        $brokerage = Organisation::factory()->ofType(OrganisationType::Brokerage)->create();

        $this->actingAs($this->ops)->patch("/admin/plans/{$this->product->id}", ['seller_organisation_id' => $brokerage->id])->assertSessionHasErrors('seller_organisation_id');
    });
});

describe('weekly payouts', function () {
    it('sends one transfer per ready payee, once, and leaves others earning', function () {
        schoolSells($this);
        sofiaBuys($this);
        readyAccount('organisation', $this->school->id);

        $result = app(PayoutRun::class)->run();

        expect($result)->toBe(['payouts' => 1, 'amount' => 30524, 'waiting' => 1]);
        $payout = Payout::query()->sole();
        expect($payout->status)->toBe('sent')->and($payout->transfer_id)->toStartWith('sim_tr_')
            ->and(PayoutLine::query()->where('payee_type', 'organisation')->sole()->status)->toBe(PayoutLineStatus::Paid)
            ->and(PayoutLine::query()->where('payee_type', 'user')->sole()->status)->toBe(PayoutLineStatus::Pending);

        expect(app(PayoutRun::class)->run()['payouts'])->toBe(0);
        $this->artisan('reos:payouts:run')->assertSuccessful();
    });

    it('transfers through Stripe Connect, and keeps the lines when Stripe refuses', function () {
        app(CredentialVault::class)->put('stripe-connect', CredentialEnvironment::Sandbox, ['client_id' => 'ca_reos', 'secret_key' => 'sk_test_reos', 'webhook_secret' => 'whsec_connect'], null);
        IntegrationSetting::query()->updateOrCreate(['integration_id' => 'stripe-connect'], ['mode' => IntegrationMode::Sandbox]);
        schoolSells($this);
        sofiaBuys($this);
        ConnectedAccount::query()->create(['owner_type' => 'organisation', 'owner_id' => $this->school->id, 'environment' => 'sandbox', 'kind' => 'express', 'stripe_account_id' => 'acct_school', 'details_submitted' => true, 'payouts_enabled' => true]);
        ConnectedAccount::query()->create(['owner_type' => 'user', 'owner_id' => $this->rachel->id, 'environment' => 'sandbox', 'kind' => 'express', 'stripe_account_id' => 'acct_rachel', 'details_submitted' => true, 'payouts_enabled' => true]);

        Http::fake([
            'api.stripe.com/v1/transfers' => Http::sequence()
                ->push(['id' => 'tr_school'])
                ->push(['error' => ['message' => 'Insufficient funds in Stripe account.']], 400)
                ->push(['error' => ['message' => 'Insufficient funds in Stripe account.']], 400)
                ->push(['error' => ['message' => 'Insufficient funds in Stripe account.']], 400),
        ]);

        $result = app(PayoutRun::class)->run();

        expect($result['payouts'])->toBe(1);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/transfers') && $r['destination'] === 'acct_school' && (int) $r['amount'] === 30524
            && str_starts_with($r->header('Idempotency-Key')[0] ?? '', 'payout:'));
        expect(Payout::query()->where('status', 'failed')->sole()->failure)->toContain('Insufficient funds')
            ->and(PayoutLine::query()->where('payee_type', 'user')->sole())->status->toBe(PayoutLineStatus::Pending)->payout_id->toBeNull();
    });

    it('takes back a paid share when the order is refunded later', function () {
        schoolSells($this);
        $order = sofiaBuys($this);
        readyAccount('organisation', $this->school->id);
        app(PayoutRun::class)->run();

        app(Checkout::class)->refunded((string) $order->gateway_payment_id);

        expect(PayoutLine::query()->where('payee_type', 'organisation')->sole()->status)->toBe(PayoutLineStatus::Reversed)
            ->and(PayoutLine::query()->where('payee_type', 'user')->sole()->status)->toBe(PayoutLineStatus::Cancelled)
            ->and(AuditEvent::query()->where('action', 'payouts.reversed')->count())->toBe(1);
    });
});

describe('sales and payouts (school screen 53)', function () {
    it('shows products, the month\'s breakdown with fees, the account and the next payout', function () {
        schoolSells($this);
        sofiaBuys($this);

        $this->actingAs($this->paul)->get('/organisation/sales')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('payouts/sales')
            ->where('statement.products.0.product', 'Texas Pre-licence Complete')
            ->where('statement.products.0.sold', 1)
            ->where('statement.totals', ['gross' => 49900, 'discounts' => 10000, 'platform_fee' => 3990, 'payees' => 5386, 'net' => 30524])
            ->where('statement.fee_percent', 10)
            ->where('statement.account', null)
            ->where('statement.pending', 30524));
    });

    it('exports the month as CSV', function () {
        schoolSells($this);
        $order = sofiaBuys($this);

        $csv = $this->actingAs($this->paul)->get('/organisation/sales/export')->assertOk()->streamedContent();

        expect($csv)->toContain('Platform fee (cents)')->toContain($order->uuid)->toContain(',49900,10000,3990,5386,30524,paid,pending');
    });

    it('sets up payouts on Stripe (the Simulator here) and audits it', function () {
        $response = $this->actingAs($this->paul)->post('/organisation/sales/connect', [], ['X-Inertia' => 'true']);
        $response->assertStatus(409);
        $account = ConnectedAccount::query()->sole();
        expect($response->headers->get('X-Inertia-Location'))->toContain("/payouts/simulate/{$account->stripe_account_id}")
            ->and($account->owner_type)->toBe('organisation')->and($account->payouts_enabled)->toBeFalse()
            ->and(AuditEvent::query()->where('action', 'payouts.account_created')->count())->toBe(1);

        $this->post("/payouts/simulate/{$account->stripe_account_id}", ['return' => url('/organisation/sales')])->assertRedirect(url('/organisation/sales'));
        expect($account->refresh()->payouts_enabled)->toBeTrue();

        // Never back to another site; never someone else's account.
        $this->post("/payouts/simulate/{$account->stripe_account_id}", ['return' => 'https://evil.example/'])->assertRedirect(route('dashboard'));
        $this->actingAs($this->sofia)->get("/payouts/simulate/{$account->stripe_account_id}")->assertNotFound();
    });

    it('starts Stripe Express onboarding with sandbox keys', function () {
        app(CredentialVault::class)->put('stripe-connect', CredentialEnvironment::Sandbox, ['client_id' => 'ca_reos', 'secret_key' => 'sk_test_reos', 'webhook_secret' => 'whsec_connect'], null);
        IntegrationSetting::query()->updateOrCreate(['integration_id' => 'stripe-connect'], ['mode' => IntegrationMode::Sandbox]);
        Http::fake([
            'api.stripe.com/v1/accounts' => Http::response(['id' => 'acct_1TRS']),
            'api.stripe.com/v1/account_links' => Http::response(['url' => 'https://connect.stripe.com/setup/e/acct_1TRS/abc']),
        ]);

        $this->actingAs($this->paul)->post('/organisation/sales/connect', [], ['X-Inertia' => 'true'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://connect.stripe.com/setup/e/acct_1TRS/abc');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/accounts') && $r['type'] === 'express' && $r['business_type'] === 'company' && $r['capabilities']['transfers']['requested'] === 'true');
        expect(ConnectedAccount::query()->sole())->stripe_account_id->toBe('acct_1TRS')->environment->toBe('sandbox');
    });

    it('connects the school\'s own Stripe account only when screen 66 allows it', function () {
        $this->actingAs($this->paul)->post('/organisation/sales/own-account', [], ['X-Inertia' => 'true'])->assertSessionHasErrors('account');

        GatewaySetting::query()->create(['gateway' => 'stripe', 'is_default' => true, 'tenant_accounts' => true]);
        $location = $this->post('/organisation/sales/own-account', [], ['X-Inertia' => 'true'])->assertStatus(409)->headers->get('X-Inertia-Location');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        $this->get('/organisation/sales/own-account?state=wrong&code=x')->assertSessionHasErrors('account');
        $this->get('/organisation/sales/own-account?'.http_build_query($query))->assertRedirect('/organisation/sales');

        expect(ConnectedAccount::query()->sole())->kind->toBe('standard')->payouts_enabled->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'payouts.own_account_connected')->sole()->properties)->toMatchArray(['from' => null, 'to' => 'standard']);
    });

    it('is for school admins only', function () {
        $this->actingAs($this->rachel)->get('/organisation/sales')->assertForbidden();
        $this->actingAs($this->sofia)->get('/organisation/sales')->assertForbidden();
    });
});

describe('earnings (instructors and mentors)', function () {
    it('shows net this month, the next payout and each share, on the web and in the app', function () {
        schoolSells($this);
        sofiaBuys($this);

        $this->actingAs($this->rachel)->get('/earnings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('payouts/earnings')
            ->where('statement.net_this_month', 5386)
            ->where('statement.pending', 5386)
            ->where('statement.lines.0.product', 'Texas Pre-licence Complete')
            ->where('statement.lines.0.role', 'instructor'));

        $this->getJson('/api/v1/me/earnings')->assertOk()->assertJson(['net_this_month' => 5386, 'fee_percent' => 10, 'account' => null]);
    });

    it('is not for other roles', function () {
        $this->actingAs($this->sofia)->get('/earnings')->assertNotFound();
        $this->getJson('/api/v1/me/earnings')->assertNotFound();
    });
});

describe('Stripe Connect webhooks', function () {
    it('refreshes the account when Stripe says it changed, once per event', function () {
        app(CredentialVault::class)->put('stripe-connect', CredentialEnvironment::Sandbox, ['client_id' => 'ca_reos', 'secret_key' => 'sk_test_reos', 'webhook_secret' => 'whsec_connect'], null);
        IntegrationSetting::query()->updateOrCreate(['integration_id' => 'stripe-connect'], ['mode' => IntegrationMode::Sandbox]);
        $account = ConnectedAccount::query()->create(['owner_type' => 'user', 'owner_id' => $this->rachel->id, 'environment' => 'sandbox', 'kind' => 'express', 'stripe_account_id' => 'acct_rachel']);
        Http::fake(['api.stripe.com/v1/accounts/acct_rachel' => Http::response(['id' => 'acct_rachel', 'details_submitted' => true, 'payouts_enabled' => true, 'requirements' => ['currently_due' => []]])]);

        $body = json_encode(['id' => 'evt_1', 'type' => 'account.updated', 'account' => 'acct_rachel', 'data' => ['object' => ['id' => 'acct_rachel']]]);
        $time = time();
        $sign = fn (string $secret) => "t={$time},v1=".hash_hmac('sha256', $time.'.'.$body, $secret);

        $this->call('POST', '/webhooks/stripe-connect', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sign('wrong')], $body)->assertStatus(400);
        $this->call('POST', '/webhooks/stripe-connect', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sign('whsec_connect')], $body)->assertOk();

        expect($account->refresh()->payouts_enabled)->toBeTrue();
    });
});
