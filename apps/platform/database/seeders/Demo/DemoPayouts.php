<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Billing\OrderStatus;
use App\Billing\Payouts\PayeeRole;
use App\Billing\Payouts\RevenueSplit;
use App\Billing\Payouts\SimulatedConnect;
use App\Models\ConnectedAccount;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Payouts as on school screen 53 and instructor screen 20 (B5.2b): Texas
 * Realty School sells Texas Pre-licence Complete and Exam Prep, Dr. Rachel
 * Hughes gets 15% of the package, the school's payouts are set up on the
 * Simulator, and a few of this month's sales are paid.
 */
final readonly class DemoPayouts
{
    public function __construct(private RevenueSplit $split) {}

    public function seed(): void
    {
        $school = Organisation::query()->where('name', 'Texas Realty School')->first();
        $rachel = User::query()->where('email', 'rachel.hughes@demo.reos.test')->first();
        $buyer = User::query()->where('email', 'sofia.reyes@demo.reos.test')->first();

        if ($school === null || $rachel === null || $buyer === null) {
            return;
        }

        $products = Product::query()->whereIn('key', ['texas-pre-licence-complete', 'texas-exam-prep'])->get();
        $products->each(static fn (Product $p) => $p->forceFill(['seller_organisation_id' => $school->id])->save());

        $complete = $products->firstWhere('key', 'texas-pre-licence-complete');

        if ($complete === null) {
            return;
        }

        $complete->splits()->updateOrCreate(['payee_user_id' => $rachel->id], ['role' => PayeeRole::Instructor, 'percent' => 15]);

        $account = ConnectedAccount::query()->firstOrCreate(
            ['owner_type' => ConnectedAccount::ORGANISATION, 'owner_id' => $school->id, 'environment' => 'simulator'],
            ['kind' => ConnectedAccount::EXPRESS, 'stripe_account_id' => 'sim_acct_texasrealty', 'details_submitted' => true, 'payouts_enabled' => true, 'requirements' => []],
        );
        SimulatedConnect::finish($account->stripe_account_id);

        $exam = $products->firstWhere('key', 'texas-exam-prep') ?? $complete;

        // Three demo sales this month (no real money), already paid.
        foreach (range(1, 3) as $n) {
            $price = Price::query()->where('product_id', $n === 3 ? $exam->id : $complete->id)->current()->first();

            if ($price === null || Order::query()->where('gateway_session_id', 'demo_payout_'.$n)->exists()) {
                continue;
            }

            $discount = $n === 1 ? 10000 : 0;
            $charge = $price->amount_cents - $discount;
            $tax = intdiv($charge * 825 + 5000, 10000);
            $order = Order::query()->create([
                'uuid' => (string) Str::uuid(), 'user_id' => $buyer->id, 'product_id' => $price->product_id, 'price_id' => $price->id,
                'state_code' => $price->state_code, 'amount_cents' => $price->amount_cents, 'discount_cents' => $discount, 'tax_cents' => $tax,
                'total_cents' => $charge + $tax, 'status' => OrderStatus::Paid, 'gateway' => 'stripe', 'environment' => 'simulator',
                'gateway_session_id' => 'demo_payout_'.$n, 'paid_at' => now()->startOfMonth()->addDays($n),
            ]);
            $this->split->record($order);
        }
    }
}
