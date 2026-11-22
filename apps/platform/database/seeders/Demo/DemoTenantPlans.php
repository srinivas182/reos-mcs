<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Billing\Buyer;
use App\Billing\PriceInterval;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use App\Models\Price;
use App\Models\Product;

/**
 * Plans a brokerage or school buys when it signs up on reos.com (A6.1b),
 * priced for Texas. REOS sets real prices on Plans and pricing (screen 67).
 */
final readonly class DemoTenantPlans
{
    public function seed(): void
    {
        $plans = [
            ['brokerage-starter', 'Brokerage Starter', Buyer::Broker, 25, 19900, ['Up to 25 agents', 'Agent onboarding and approvals', 'CE compliance for every agent', 'Your brand and domain'], ['broker.core']],
            ['brokerage-growth', 'Brokerage Growth', Buyer::Broker, 100, 59900, ['Up to 100 agents', 'Teams and routing', 'Mentor program', 'Everything in Starter'], ['broker.core', 'broker.teams']],
            ['school-essentials', 'School Essentials', Buyer::School, 200, 29900, ['Up to 200 students', 'Course catalogue and state approvals', 'CE reporting', 'Sales and payouts'], ['school.core']],
        ];

        foreach ($plans as [$key, $name, $buyer, $seats, $cents, $includes, $features]) {
            $product = Product::query()->updateOrCreate(['key' => $key], [
                'name' => $name, 'kind' => ProductKind::Plan, 'buyer' => $buyer, 'includes' => $includes, 'features' => $features,
                'seats' => $seats, 'status' => ProductStatus::Live,
            ]);

            if (! Price::query()->where('product_id', $product->id)->where('state_code', 'TX')->current()->exists()) {
                Price::query()->create(['product_id' => $product->id, 'state_code' => 'TX', 'amount_cents' => $cents, 'interval' => PriceInterval::Month, 'currency' => 'usd', 'starts_at' => now()->subMonth()]);
            }
        }
    }
}
