<?php

declare(strict_types=1);

namespace App\ProviderNetwork;

use App\Billing\Buyer;
use App\Billing\PriceInterval;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProviderCategory;
use App\Models\ProviderCategoryPrice;
use Illuminate\Support\Facades\DB;

/**
 * Provider plans (B15.2, decision 4): Listed, Pro and Partner, the super
 * admin's flat monthly fee for the category and state (RESPA: never per
 * lead, referral or deal). Each is sold through REOS checkout as a product
 * kept in step with the category's price.
 *
 * - **Listed** (`basic`): 1 service area.
 * - **Pro** (`pro`): the category's areas on Pro.
 * - **Partner** (`premium`): 10 areas.
 *
 * @phpstan-type Plan array{tier: 'basic'|'pro'|'premium', monthly: int, areas: int}
 */
final readonly class ProviderPlans
{
    public const PARTNER_AREAS = 10;

    public static function areas(ProviderCategory $category, string $tier): int
    {
        return match ($tier) {
            'basic' => 1,
            'pro' => max(1, $category->areas_on_pro),
            default => self::PARTNER_AREAS,
        };
    }

    /** @return list<Plan> the plans priced in the state, cheapest first */
    public function for(ProviderCategory $category, string $state): array
    {
        $plans = [];

        foreach (ProviderCategory::TIERS as $tier) {
            $cents = $category->prices()->where('state_code', $state)->where('tier', $tier)->value('monthly_cents');

            if (is_int($cents)) {
                $plans[] = ['tier' => $tier, 'monthly' => $cents, 'areas' => self::areas($category, $tier)];
            }
        }

        return $plans;
    }

    /** The checkout price for a plan, made or brought in step with the admin's price. */
    public function price(ProviderCategory $category, string $tier, string $state): ?Price
    {
        $row = $category->prices()->where('state_code', $state)->where('tier', $tier)->first();

        if (! $row instanceof ProviderCategoryPrice) {
            return null;
        }

        return DB::transaction(function () use ($category, $tier, $state, $row): Price {
            $product = Product::query()->firstOrCreate(['key' => "provider-{$category->key}-{$tier}"], [
                'name' => $category->name.' · '.ucfirst(self::tierName($tier)), 'kind' => ProductKind::Plan, 'buyer' => Buyer::Provider,
                'status' => ProductStatus::Live, 'includes' => [], 'features' => [],
            ]);
            $current = Price::query()->where('product_id', $product->id)->where('state_code', $state)->current()->first();

            if ($current !== null && $current->amount_cents === $row->monthly_cents) {
                return $current;
            }

            $current?->forceFill(['ends_at' => now()])->save();

            return Price::query()->create([
                'product_id' => $product->id, 'state_code' => $state, 'amount_cents' => $row->monthly_cents, 'currency' => 'usd',
                'interval' => PriceInterval::Month, 'starts_at' => now()->subSecond(),
            ]);
        });
    }

    /** The tier a provider product sells. */
    public static function tierOf(Product $product): ?string
    {
        foreach (ProviderCategory::TIERS as $tier) {
            if (str_starts_with($product->key, 'provider-') && str_ends_with($product->key, "-{$tier}")) {
                return $tier;
            }
        }

        return null;
    }

    private static function tierName(string $tier): string
    {
        return match ($tier) {
            'basic' => 'listed',
            'pro' => 'pro',
            default => 'partner',
        };
    }
}
