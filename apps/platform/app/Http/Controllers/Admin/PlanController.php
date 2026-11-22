<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Billing\Buyer;
use App\Billing\Membership;
use App\Billing\Payouts\RevenueSplit;
use App\Billing\Plans;
use App\Billing\PriceInterval;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\PriceRequest;
use App\Http\Requests\Admin\Billing\ProductRequest;
use App\Http\Requests\Admin\Billing\SplitsRequest;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductSplit;
use App\Models\State;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plans and pricing (web journey screen 67, B5.1): every product REOS sells,
 * tabbed by buyer, with its price per state, what it includes, AI help and
 * status; new plans, edits and the price history. Candidates are never
 * charged AI credits.
 */
final class PlanController extends Controller
{
    /** Tabs, in the journey's order. */
    public const TABS = ['agents', 'candidates', 'providers', 'schools', 'associations', 'credit_packs'];

    public function index(Request $request): Response
    {
        $tab = $request->validate(['tab' => ['nullable', Rule::in(self::TABS)]])['tab'] ?? 'candidates';
        $tab = is_string($tab) ? $tab : 'candidates';
        $names = State::query()->pluck('name', 'code')->all();

        $products = Product::query()
            ->with(['prices' => static fn ($q) => $q->orderBy('state_code')->orderByDesc('starts_at'), 'seller:id,name', 'splits.payee:id,name,email'])
            ->when($tab === 'credit_packs', static fn ($q) => $q->where('kind', ProductKind::CreditPack))
            ->when($tab !== 'credit_packs', static fn ($q) => $q->where('kind', '!=', ProductKind::CreditPack)->whereIn('buyer', self::buyers($tab)))
            ->orderByRaw("CASE status WHEN 'live' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/plans/index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'products' => $products->map(static fn (Product $product): array => [
                'id' => $product->id,
                'key' => $product->key,
                'name' => $product->name,
                'kind' => $product->kind->value,
                'buyer' => $product->buyer->value,
                'description' => $product->description,
                'includes' => $product->includes ?? [],
                'features' => $product->features ?? [],
                'ai_help' => $product->ai_help,
                'credits' => $product->credits,
                'seats' => $product->seats,
                'status' => $product->status->value,
                // Who sells it and who shares in its sales (B5.2b).
                'seller' => $product->seller === null ? null : ['id' => $product->seller->id, 'name' => $product->seller->name],
                'splits' => $product->splits->map(static fn (ProductSplit $split): array => [
                    'email' => $split->payee->email, 'name' => $split->payee->name, 'role' => $split->role->value, 'percent' => $split->percent,
                ])->values()->all(),
                'prices' => $product->prices->map(static fn (Price $price): array => [
                    'id' => $price->id,
                    'state' => $price->state_code,
                    'state_name' => is_string($names[$price->state_code] ?? null) ? $names[$price->state_code] : $price->state_code,
                    'amount' => $price->amount_cents,
                    'floor' => $price->floor_cents,
                    'interval' => $price->interval->value,
                    'current' => $price->isCurrent(),
                    'starts_at' => $price->starts_at->toIso8601String(),
                    'ends_at' => $price->ends_at?->toIso8601String(),
                ])->values()->all(),
            ])->values()->all(),
            'iosBuyLink' => app(Membership::class)->iosBuyLink(),
            'options' => [
                'kinds' => ProductKind::values(),
                'buyers' => Buyer::values(),
                'intervals' => PriceInterval::values(),
                'statuses' => ProductStatus::values(),
                'states' => State::query()->orderBy('name')->get(['code', 'name'])->map(static fn (State $s): array => ['code' => $s->code, 'name' => $s->name])->values()->all(),
                'schools' => Organisation::query()->where('type', OrganisationType::School)->orderBy('name')->get(['id', 'name'])
                    ->map(static fn (Organisation $o): array => ['id' => $o->id, 'name' => $o->name])->values()->all(),
                'fee_percent' => RevenueSplit::platformFeePercent(),
            ],
        ]);
    }

    public function store(ProductRequest $request, #[CurrentUser] User $actor, Plans $plans): RedirectResponse
    {
        $product = $plans->create($request->validated(), $actor);
        $tab = $product->kind === ProductKind::CreditPack ? 'credit_packs' : self::tabFor($product->buyer);

        return to_route('plans.index', ['tab' => $tab]);
    }

    public function update(ProductRequest $request, Product $product, #[CurrentUser] User $actor, Plans $plans): RedirectResponse
    {
        $plans->update($product, $request->validated(), $actor);

        return back();
    }

    public function status(Request $request, Product $product, #[CurrentUser] User $actor, Plans $plans): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(ProductStatus::values())]])['status'];
        $plans->setStatus($product, ProductStatus::from(is_string($status) ? $status : ''), $actor);

        return back();
    }

    public function price(PriceRequest $request, Product $product, #[CurrentUser] User $actor, Plans $plans): RedirectResponse
    {
        $plans->setPrice($product, $request->string('state')->toString(), PriceInterval::from($request->string('interval')->toString()), (int) $request->cents('amount'), $request->cents('floor'), $actor);

        return back();
    }

    public function endPrice(Product $product, Price $price, #[CurrentUser] User $actor, Plans $plans): RedirectResponse
    {
        abort_unless($price->product_id === $product->id, 404);
        $plans->endPrice($price, $actor);

        return back();
    }

    /** Instructors' and mentors' shares of a school product's sales (B5.2b). */
    public function splits(SplitsRequest $request, Product $product, #[CurrentUser] User $actor, Plans $plans): RedirectResponse
    {
        $plans->setSplits($product, $request->splits(), $actor);

        return back();
    }

    /** "Email me the link" (default) or a link to reos.com from the iPhone app's Membership screen. */
    public function iosBuyLink(Request $request, #[CurrentUser] User $actor, Membership $membership): RedirectResponse
    {
        $mode = $request->validate(['mode' => ['required', Rule::in([Membership::EMAIL, Membership::LINK])]])['mode'];
        $membership->setIosBuyLink(is_string($mode) ? $mode : Membership::EMAIL, $actor);

        return back();
    }

    /** @return list<string> */
    private static function buyers(string $tab): array
    {
        return match ($tab) {
            'agents' => [Buyer::Agent->value, Buyer::Broker->value],
            'providers' => [Buyer::Provider->value],
            'schools' => [Buyer::School->value],
            'associations' => [Buyer::Association->value],
            default => [Buyer::Candidate->value],
        };
    }

    private static function tabFor(Buyer $buyer): string
    {
        return match ($buyer) {
            Buyer::Agent, Buyer::Broker => 'agents',
            Buyer::Provider => 'providers',
            Buyer::School => 'schools',
            Buyer::Association => 'associations',
            Buyer::Candidate => 'candidates',
        };
    }
}
