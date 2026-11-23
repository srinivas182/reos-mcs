<?php

declare(strict_types=1);

namespace App\Billing;

use App\Models\Price;
use App\Models\SponsorDiscount;
use App\Models\State;
use App\Models\User;

/**
 * The candidate's packages on reos.com (A8.3b; web journey screen 3, the
 * Membership screen's link): live plans for candidates in a state with
 * their current prices (screen 67), what's included, the sponsor discounts
 * schools offer, and, for someone signed in, their own price. The phone app
 * never shows a price.
 *
 * @phpstan-type Package array{id: int, product: string, kind: string, description: string|null, includes: list<string>, interval: string, amount: int, charge: int|null, sponsor: string|null, discounts: list<array{sponsor: string, amount: int}>}
 */
final readonly class Packages
{
    public function __construct(private Checkout $checkout) {}

    /** @return list<Package> */
    public function in(string $state, ?User $viewer): array
    {
        $prices = Price::query()->with('product')->current()->where('state_code', $state)
            ->whereHas('product', static fn ($q) => $q->where('buyer', Buyer::Candidate)->where('status', ProductStatus::Live))
            ->orderBy('amount_cents')->get();
        $discounts = SponsorDiscount::query()->with('sponsor')->where('active', true)->whereIn('product_id', $prices->pluck('product_id'))->get()->groupBy('product_id');
        $list = [];

        foreach ($prices as $price) {
            $quote = $viewer === null ? null : $this->checkout->quote($viewer, $price);
            $offers = [];
            foreach ($discounts->get($price->product_id) ?? [] as $discount) {
                $off = min($discount->amount_cents, max(0, $price->amount_cents - ($price->floor_cents ?? 0)));
                if ($off > 0) {
                    $offers[] = ['sponsor' => $discount->sponsor->name, 'amount' => $off];
                }
            }

            $list[] = [
                'id' => $price->id, 'product' => $price->product->name, 'kind' => $price->product->kind->value, 'description' => $price->product->description,
                'includes' => $price->product->includes ?? [], 'interval' => $price->interval->value, 'amount' => $price->amount_cents,
                'charge' => $quote === null ? null : $quote['charge'], 'sponsor' => $quote === null ? null : $quote['sponsor']?->name, 'discounts' => $offers,
            ];
        }

        return $list;
    }

    /**
     * States where candidates can buy a package now.
     *
     * @return list<array{code: string, name: string}>
     */
    public function states(): array
    {
        $codes = Price::query()->current()->whereHas('product', static fn ($q) => $q->where('buyer', Buyer::Candidate)->where('status', ProductStatus::Live))->distinct()->pluck('state_code');
        $list = [];

        foreach (State::query()->whereIn('code', $codes)->orderBy('name')->get() as $state) {
            $list[] = ['code' => $state->code, 'name' => $state->name];
        }

        return $list;
    }
}
