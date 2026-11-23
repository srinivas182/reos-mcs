<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Billing\Ledger\MoneyPostings;
use App\Billing\Ledger\Points;
use App\Billing\Ledger\Reconciliation;
use App\Billing\OrderStatus;
use App\Billing\Payouts\PayoutRun;
use App\Billing\Refunds;
use App\Enums\OrganisationType;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\ConnectedAccount;
use App\Models\CourseProgress;
use App\Models\Entitlement;
use App\Models\IntegrationSetting;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\ReconciliationRun;
use App\Models\Refund;
use App\Models\SponsorCode;
use App\Models\User;
use App\Services\Ai\AiCredits;
use App\Services\Content\Courses;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School', 'slug' => 'texas-realty-school']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Sofia Reyes']);
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    app(DemoBilling::class)->seed();
    $this->complete = Price::query()->whereHas('product', fn ($q) => $q->where('key', 'texas-pre-licence-complete'))->sole();
    $this->ledger = app(Ledger::class);
});

function buy(object $test, User $buyer, Price $price): Order
{
    $started = $test->actingAs($buyer)->postJson("/checkout/{$price->id}")->assertCreated();
    $order = Order::query()->where('uuid', $started->json('order'))->sole();
    $test->post("/checkout/orders/{$order->uuid}/simulate");
    $test->get("/checkout/orders/{$order->uuid}/return")->assertOk();

    return $order->refresh();
}

function balanceOf(string $key): int
{
    return LedgerAccount::query()->where('key', $key)->value('balance') ?? 0;
}

describe('the ledger', function () {
    it('only posts transactions that balance, and never edits an entry', function () {
        $a = $this->ledger->platform('revenue');
        $b = $this->ledger->platform('refunds');

        expect(fn () => $this->ledger->post('test', [[$a, 100], [$b, -99]]))->toThrow(InvalidArgumentException::class);

        $this->ledger->post('test', [[$a, 100], [$b, -100]]);
        $entry = LedgerEntry::query()->first();

        expect(fn () => $entry->update(['amount' => 5]))->toThrow(LogicException::class)
            ->and(fn () => $entry->delete())->toThrow(LogicException::class)
            ->and(Reconciliation::integrity())->toBe([]);
    });

    it('records a paid order: what the gateway holds, tax owed, the sponsor discount and revenue', function () {
        $order = buy($this, $this->sofia, $this->complete);
        $tax = (int) round(39900 * 0.0825);

        expect(balanceOf('clearing:stripe:simulator'))->toBe(39900 + $tax)
            ->and(balanceOf('platform:sales_tax'))->toBe(-$tax)
            ->and(balanceOf('platform:sponsor_discounts'))->toBe(10000)
            ->and(balanceOf('platform:revenue'))->toBe(-49900)
            ->and(LedgerEntry::query()->where('reference_id', $order->uuid)->sum('amount'))->toEqual(0);

        // Counted once, even when the webhook and the return both arrive.
        app(MoneyPostings::class)->orderPaid($order);
        expect(LedgerEntry::query()->where('reason', 'order_paid')->count())->toBe(4);
    });

    it('follows a school sale through payout and refund back to zero', function () {
        $this->complete->product->forceFill(['seller_organisation_id' => $this->school->id])->save();
        $order = buy($this, $this->sofia, $this->complete);
        expect(balanceOf("organisation:{$this->school->id}:usd"))->toBe(-35910)
            ->and(balanceOf('platform:revenue'))->toBe(-(3990 + 10000));

        ConnectedAccount::query()->create(['owner_type' => 'organisation', 'owner_id' => $this->school->id, 'environment' => 'simulator', 'kind' => 'express', 'stripe_account_id' => 'sim_acct_x', 'details_submitted' => true, 'payouts_enabled' => true]);
        app(PayoutRun::class)->run();
        expect(balanceOf("organisation:{$this->school->id}:usd"))->toBe(0);

        $this->actingAs($this->ops)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->post("/admin/gateways/orders/{$order->uuid}/refund", ['reason' => 'Changed mind on day 2'])->assertSessionHasNoErrors();

        expect(LedgerAccount::query()->where('unit', 'usd')->pluck('balance', 'key')->filter()->all())->toBe([])
            ->and(Reconciliation::integrity())->toBe([]);
    });
});

describe('AI credits', function () {
    it('grants the plan\'s monthly credits, spends them before top-ups, and expires them at month end', function () {
        $credits = app(AiCredits::class);
        expect($credits->left($this->james))->toBe(500);

        $pack = Price::query()->whereHas('product', fn ($q) => $q->where('key', 'ai-credits-1000'))->sole();
        buy($this, $this->james, $pack);
        expect($credits->left($this->james))->toBe(1500);

        $credits->charge($this->james, 'voice', 'ai', 499);
        $credits->charge($this->james, 'voice', 'ai', 3);
        expect($credits->summary($this->james))->toMatchArray(['monthly' => 0, 'topup' => 998, 'used' => 502]);

        $this->travelTo(CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth()->addHour());
        expect($credits->summary($this->james))->toMatchArray(['monthly' => 500, 'topup' => 998])
            ->and(Reconciliation::integrity())->toBe([]);
    });

    it('never charges a candidate: the sponsor pool pays, else the platform', function () {
        $credits = app(AiCredits::class);
        $this->actingAs($this->ops)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->post("/admin/tenants/{$this->school->id}/pool", ['credits' => 2, 'seats' => 0, 'note' => 'Invoice INV-1042'])->assertSessionHasNoErrors();

        $credits->charge($this->sofia, 'ask', 'ai', 2);
        $credits->charge($this->sofia, 'ask', 'ai', 1);

        expect(balanceOf("organisation:{$this->school->id}:credits"))->toBe(0)
            ->and(balanceOf('platform:ai_used'))->toBe(3)
            ->and($credits->summary($this->sofia)['sponsored'])->toBeTrue()
            ->and($credits->canSpend($this->sofia, 1000))->toBeTrue()
            ->and(balanceOf("person:{$this->sofia->id}:topup"))->toBe(0);
    });

    it('shows credits on Plan and billing', function () {
        $this->actingAs($this->james)->get('/settings/billing')->assertInertia(fn (Assert $page) => $page
            ->where('credits.monthly', 500)->where('credits.allowance', 500)->where('credits.sponsored', false));
    });
});

describe('sponsor pools and points', function () {
    it('draws seats for codes from the pool, refuses more than it has, and returns unused seats', function () {
        $this->actingAs($this->ops)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->post("/admin/tenants/{$this->school->id}/pool", ['credits' => 0, 'seats' => 10, 'note' => 'Contract 2027'])->assertSessionHasNoErrors();
        expect(AuditEvent::query()->where('action', 'pool.funded')->sole()->properties)->toMatchArray(['before' => ['credits' => 0, 'seats' => 0], 'after' => ['credits' => 0, 'seats' => 10]]);

        $this->post("/admin/tenants/{$this->school->id}/codes", ['product_id' => $this->complete->product_id, 'state' => 'TX', 'seats' => 8])->assertSessionHasNoErrors();
        $this->post("/admin/tenants/{$this->school->id}/codes", ['product_id' => $this->complete->product_id, 'state' => 'TX', 'seats' => 5])->assertSessionHasErrors('seats');
        expect(balanceOf("organisation:{$this->school->id}:seats"))->toBe(2);

        $code = SponsorCode::query()->sole();
        $this->delete("/admin/tenants/{$this->school->id}/codes/{$code->id}");
        expect(balanceOf("organisation:{$this->school->id}:seats"))->toBe(10);
    });

    it('awards points once per milestone and never spends more than there are', function () {
        $points = app(Points::class);

        expect($points->award($this->sofia, 50, 'course:12:done'))->toBeTrue()
            ->and($points->award($this->sofia, 50, 'course:12:done'))->toBeFalse()
            ->and($points->balance($this->sofia))->toBe(50);

        $points->redeem($this->sofia, 30, 'session:44');
        expect($points->balance($this->sofia))->toBe(20)
            ->and(fn () => $points->redeem($this->sofia, 30, 'session:45'))->toThrow(ValidationException::class);
    });
});

describe('refunds from REOS (screen 66)', function () {
    it('refunds in full within the policy: access ends and the ledger reverses', function () {
        $order = buy($this, $this->sofia, $this->complete);

        $this->actingAs($this->ops)->get('/admin/gateways')->assertInertia(fn (Assert $page) => $page
            ->where('orders.0.uuid', $order->uuid)
            ->where('orders.0.policy.within_policy', true)
            ->where('orders.0.policy.refundable', $order->total_cents));

        $this->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->post("/admin/gateways/orders/{$order->uuid}/refund", ['reason' => 'Changed mind'])->assertSessionHasNoErrors();

        expect($order->refresh()->status)->toBe(OrderStatus::Refunded)
            ->and(Entitlement::query()->active()->where('user_id', $this->sofia->id)->count())->toBe(0)
            ->and(Refund::query()->sole())->full->toBeTrue()->within_policy->toBeTrue()->status->toBe('done')
            ->and(balanceOf('clearing:stripe:simulator'))->toBe(0)
            ->and(AuditEvent::query()->where('action', 'order.refund_issued')->sole()->properties)->toMatchArray(['full' => true, 'reason' => 'Changed mind']);
    });

    it('refunds part: access stays, and no more than what is left', function () {
        $order = buy($this, $this->sofia, $this->complete);
        $this->actingAs($this->ops)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $this->post("/admin/gateways/orders/{$order->uuid}/refund", ['amount' => '50', 'reason' => 'Goodwill'])->assertSessionHasNoErrors();
        $this->post("/admin/gateways/orders/{$order->uuid}/refund", ['amount' => '1000', 'reason' => 'Too much'])->assertSessionHasErrors('amount');

        expect($order->refresh()->status)->toBe(OrderStatus::Paid)
            ->and(Entitlement::query()->active()->where('user_id', $this->sofia->id)->count())->toBeGreaterThan(0)
            ->and(balanceOf('platform:refunds'))->toBe(5000)
            ->and(Refund::query()->sole()->within_policy)->toBeFalse();
    });

    it('marks a refund outside the policy once more of a course is done than allowed (A8.2)', function () {
        $order = buy($this, $this->sofia, $this->complete);
        $course = app(Courses::class)->create(['title' => 'Principles of Real Estate I', 'type' => 'qualifying', 'states' => ['TX']], $this->ops);
        $refunds = app(Refunds::class);

        CourseProgress::query()->create(['user_id' => $this->sofia->id, 'course_id' => $course->id, 'percent' => 10, 'lessons_total' => 8]);
        expect($refunds->policy($order))->toMatchArray(['within_policy' => true, 'progress_percent' => 10, 'max_progress_percent' => 25]);

        CourseProgress::query()->where('course_id', $course->id)->update(['percent' => 40]);
        expect($refunds->policy($order))->toMatchArray(['within_policy' => false, 'progress_percent' => 40]);
    });

    it('marks a refund after the window as outside the policy', function () {
        $order = buy($this, $this->sofia, $this->complete);
        $this->travel(10)->days();

        $this->actingAs($this->ops)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->post("/admin/gateways/orders/{$order->uuid}/refund", ['reason' => 'Exam moved; agreed by phone'])->assertSessionHasNoErrors();

        expect(Refund::query()->sole()->within_policy)->toBeFalse();
    });

    it('follows the super admin\'s refund policy: full only, or none', function () {
        $order = buy($this, $this->sofia, $this->complete);
        $this->actingAs($this->ops)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $this->put('/admin/gateways/refund-policy', ['mode' => 'full_only', 'window_days' => 14, 'max_progress_percent' => 20])->assertSessionHasNoErrors();
        expect(AuditEvent::query()->where('action', 'platform.setting_changed')->where('subject_id', 'billing.refund_policy')->sole()->properties)
            ->toMatchArray(['from' => ['mode' => 'full_and_partial', 'window_days' => 7, 'max_progress_percent' => 25], 'to' => ['mode' => 'full_only', 'window_days' => 14, 'max_progress_percent' => 20]]);
        $this->get('/admin/gateways')->assertInertia(fn (Assert $page) => $page
            ->where('refund_policy.mode', 'full_only')
            ->where('orders.0.policy.mode', 'full_only')
            ->where('orders.0.policy.window_days', 14));

        $this->post("/admin/gateways/orders/{$order->uuid}/refund", ['amount' => '50', 'reason' => 'Goodwill'])->assertSessionHasErrors('amount');

        $this->put('/admin/gateways/refund-policy', ['mode' => 'none', 'window_days' => 14, 'max_progress_percent' => 20]);
        $this->post("/admin/gateways/orders/{$order->uuid}/refund", ['reason' => 'Asked nicely'])->assertSessionHasErrors('amount');
        $this->put('/admin/gateways/refund-policy', ['mode' => 'sometimes', 'window_days' => 14, 'max_progress_percent' => 20])->assertSessionHasErrors('mode');

        expect(Refund::query()->count())->toBe(0)->and($order->refresh()->status)->toBe(OrderStatus::Paid);
    });

    it('refunds through Stripe once per refund', function () {
        app(CredentialVault::class)->put('stripe', CredentialEnvironment::Sandbox, ['publishable_key' => 'pk_test', 'secret_key' => 'sk_test', 'webhook_secret' => 'whsec'], null);
        IntegrationSetting::query()->updateOrCreate(['integration_id' => 'stripe'], ['mode' => IntegrationMode::Sandbox]);
        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $this->sofia->id, 'product_id' => $this->complete->product_id, 'price_id' => $this->complete->id,
            'state_code' => 'TX', 'amount_cents' => 49900, 'discount_cents' => 0, 'tax_cents' => 0, 'total_cents' => 49900, 'status' => OrderStatus::Paid,
            'gateway' => 'stripe', 'environment' => 'sandbox', 'gateway_payment_id' => 'pi_123', 'paid_at' => now(),
        ]);
        Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1'])]);

        $this->actingAs($this->ops)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->post("/admin/gateways/orders/{$order->uuid}/refund", ['amount' => '100', 'reason' => 'Goodwill'])->assertSessionHasNoErrors();

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/refunds') && $r['payment_intent'] === 'pi_123' && (int) $r['amount'] === 10000
            && str_starts_with($r->header('Idempotency-Key')[0] ?? '', 'refund:'));
        expect(Refund::query()->sole()->gateway_refund_id)->toBe('re_1');
    });
});

describe('daily reconciliation', function () {
    it('matches the Simulator\'s orders and shows the run on screen 66', function () {
        buy($this, $this->sofia, $this->complete);
        $this->artisan('reos:ledger:reconcile', ['--day' => now()->toDateString()])->assertSuccessful();

        expect(ReconciliationRun::query()->sole())->status->toBe('matched')->matched->toBe(1);
        $this->actingAs($this->ops)->get('/admin/gateways')->assertInertia(fn (Assert $page) => $page->where('reconciliation.0.status', 'matched'));
    });

    it('lists what Stripe and the ledger disagree on', function () {
        app(CredentialVault::class)->put('stripe', CredentialEnvironment::Sandbox, ['publishable_key' => 'pk_test', 'secret_key' => 'sk_test', 'webhook_secret' => 'whsec'], null);
        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $this->sofia->id, 'product_id' => $this->complete->product_id, 'price_id' => $this->complete->id,
            'state_code' => 'TX', 'amount_cents' => 49900, 'discount_cents' => 0, 'tax_cents' => 0, 'total_cents' => 49900, 'status' => OrderStatus::Paid,
            'gateway' => 'stripe', 'environment' => 'sandbox', 'gateway_payment_id' => 'pi_1', 'paid_at' => now()->subDay(),
        ]);
        app(MoneyPostings::class)->orderPaid($order);
        Http::fake(['api.stripe.com/v1/balance_transactions*' => Http::response(['has_more' => false, 'data' => [
            ['id' => 'txn_1', 'type' => 'charge', 'amount' => 49900, 'source' => ['id' => 'ch_1', 'payment_intent' => 'pi_1']],
            ['id' => 'txn_2', 'type' => 'charge', 'amount' => 1500, 'source' => ['id' => 'ch_2', 'payment_intent' => 'pi_unknown']],
        ]])]);

        $run = app(Reconciliation::class)->run(CarbonImmutable::yesterday())[0];

        expect($run->status)->toBe('differences')->and($run->matched)->toBe(1)
            ->and($run->differences)->toBe([['kind' => 'order', 'reference' => 'pi_unknown', 'ledger' => null, 'gateway' => 1500]]);
    });
});
