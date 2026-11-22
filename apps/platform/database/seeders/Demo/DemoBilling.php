<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Billing\Buyer;
use App\Billing\Entitlements;
use App\Billing\EntitlementSource;
use App\Billing\PriceInterval;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\Product;
use App\Models\SponsorDiscount;
use App\Models\User;

/**
 * Plans and prices as on web journey screen 67 (B5.1): the Texas candidate
 * packages (Pre-licence Complete $499, Monthly $79, Exam Prep only $129),
 * Louisiana as a draft, Agent Core for agents, the provider plans and a
 * credit pack. Texas Realty School pays $100 of Pre-licence Complete for
 * its students (screen 3), and James Miller has Agent Core. Safe to run
 * again; prices aren't duplicated.
 */
final readonly class DemoBilling
{
    public function __construct(private Entitlements $entitlements) {}

    public function seed(): void
    {
        $complete = $this->product('texas-pre-licence-complete', 'Texas Pre-licence Complete', ProductKind::Package, Buyer::Candidate,
            ['All 6 qualifying courses (180 hours), TREC approved', 'Exam success course, test prep, full simulations', 'Free answers from your course content', '2 instructor sessions included', 'Pass Promise: retake plan if you don’t pass'],
            ['learn.qualifying', 'learn.test_prep'], 'Course answers free · sponsor fallback', ProductStatus::Live);
        $this->price($complete, 'TX', PriceInterval::Once, 49900, 29900);

        $monthly = $this->product('texas-pre-licence-monthly', 'Texas Pre-licence Monthly', ProductKind::Plan, Buyer::Candidate,
            ['All courses while subscribed'], ['learn.qualifying', 'learn.test_prep'], 'Course answers free', ProductStatus::Live);
        $this->price($monthly, 'TX', PriceInterval::Month, 7900, null);

        $prep = $this->product('texas-exam-prep', 'Texas Exam Prep only', ProductKind::Package, Buyer::Candidate,
            ['Test prep, simulations, exam success'], ['learn.test_prep'], 'Course answers free', ProductStatus::Live);
        $this->price($prep, 'TX', PriceInterval::Once, 12900, null);

        $this->product('louisiana-pre-licence', 'Louisiana Pre-licence', ProductKind::Package, Buyer::Candidate, [], ['learn.qualifying'], null, ProductStatus::Draft);

        $core = $this->product('agent-core', 'Agent Core', ProductKind::Plan, Buyer::Agent,
            ['CE for your license, tracked to renewal', 'Electives', 'AI help: 500 credits a month'], ['learn.ce', 'learn.elective', 'agent.core'], '500 AI credits a month', ProductStatus::Live, 500);
        $this->price($core, 'TX', PriceInterval::Month, 2900, null);

        foreach ([['listed', 'Listed', 0], ['pro', 'Pro', 4900], ['partner', 'Partner', 14900]] as [$key, $name, $cents]) {
            $plan = $this->product('provider-'.$key, $name, ProductKind::Plan, Buyer::Provider, ['Profile in agents’ service lists'], ['provider.'.$key], null, ProductStatus::Live);
            $this->price($plan, 'TX', PriceInterval::Month, $cents, null);
        }

        $credits = $this->product('ai-credits-1000', '1,000 AI credits', ProductKind::CreditPack, Buyer::Agent, ['Top-up credits that never expire'], [], null, ProductStatus::Live, 1000);
        $this->price($credits, 'TX', PriceInterval::Once, 1500, null);

        $school = Organisation::query()->where('name', 'Texas Realty School')->first();

        if ($school !== null) {
            SponsorDiscount::query()->updateOrCreate(['sponsor_organisation_id' => $school->id, 'product_id' => $complete->id], ['amount_cents' => 10000, 'active' => true]);
        }

        $james = User::query()->where('email', 'james.miller@'.Personas::DOMAIN)->first();

        if ($james !== null) {
            $this->entitlements->grant($james, ['learn.ce', 'learn.elective', 'agent.core'], EntitlementSource::Admin, null, 'TX');
        }
    }

    /**
     * @param  list<string>  $includes
     * @param  list<string>  $features
     */
    private function product(string $key, string $name, ProductKind $kind, Buyer $buyer, array $includes, array $features, ?string $aiHelp, ProductStatus $status, ?int $credits = null): Product
    {
        return Product::query()->updateOrCreate(['key' => $key], [
            'name' => $name, 'kind' => $kind, 'buyer' => $buyer, 'includes' => $includes, 'features' => $features,
            'ai_help' => $aiHelp, 'status' => $status, 'credits' => $credits,
        ]);
    }

    private function price(Product $product, string $state, PriceInterval $interval, int $cents, ?int $floor): void
    {
        $current = Price::query()->where('product_id', $product->id)->where('state_code', $state)->where('interval', $interval)->current()->first();

        if ($current === null) {
            Price::query()->create(['product_id' => $product->id, 'state_code' => $state, 'amount_cents' => $cents, 'floor_cents' => $floor, 'interval' => $interval, 'currency' => 'usd', 'starts_at' => now()->subMonth()]);
        }
    }
}
