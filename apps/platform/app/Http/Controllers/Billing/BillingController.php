<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Billing\Buyer;
use App\Billing\Checkout;
use App\Billing\Gateways\Gateways;
use App\Billing\ProductStatus;
use App\Billing\SponsorCodes;
use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Price;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\States\PersonStates;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Plan and billing (B5.1; web journey screen 29 and each role's equivalent):
 * the plans for the person's role and state with what each includes, what
 * they have now, their subscriptions (cards and cancelling at the
 * gateway's portal), and every order with its receipt.
 */
final class BillingController extends Controller
{
    public function index(#[CurrentUser] User $user, ActiveRole $role, PersonStates $states, Checkout $checkout): Response
    {
        $buyer = Buyer::forRole($role->for($user)?->role);
        $state = $states->current($user);

        $prices = $buyer === null ? collect() : Price::query()
            ->with('product')
            ->current()
            ->where('state_code', $state)
            ->whereHas('product', static fn ($q) => $q->where('buyer', $buyer)->where('status', ProductStatus::Live))
            ->orderBy('amount_cents')
            ->get();

        return Inertia::render('settings/billing', [
            'state' => CheckoutController::stateName($state),
            'plans' => $prices->map(static function (Price $price) use ($user, $checkout): array {
                $quote = $checkout->quote($user, $price);

                return [...CheckoutController::price($price), 'charge' => $quote['charge'], 'sponsor' => $quote['sponsor']?->name, 'ai_help' => $price->product->ai_help];
            })->values()->all(),
            'access' => Entitlement::query()->active()->where('user_id', $user->id)->orderBy('feature')->get()
                ->map(static fn (Entitlement $e): array => ['feature' => $e->feature, 'source' => $e->source->value, 'state' => $e->state_code, 'ends_at' => $e->ends_at?->toIso8601String()])->values()->all(),
            'subscriptions' => Subscription::query()->with('product')->where('user_id', $user->id)->latest()->get()
                ->map(static fn (Subscription $s): array => [
                    'id' => $s->id,
                    'product' => $s->product->name,
                    'status' => $s->status->value,
                    'current_period_end' => $s->current_period_end?->toIso8601String(),
                    'grace_ends_at' => $s->grace_ends_at?->toIso8601String(),
                ])->values()->all(),
            'orders' => Order::query()->with('product')->where('user_id', $user->id)->whereIn('status', ['paid', 'refunded'])->latest()->limit(50)->get()
                ->map(static fn (Order $o): array => [
                    'uuid' => $o->uuid,
                    'product' => $o->product->name,
                    'status' => $o->status->value,
                    'total' => $o->total_cents,
                    'paid_at' => $o->paid_at?->toIso8601String(),
                    'receipt_url' => $o->receipt_url,
                ])->values()->all(),
        ]);
    }

    /** "Enter a code" on the web: a sponsor's code unlocks what it pays for (B5.1b). */
    public function redeem(Request $request, #[CurrentUser] User $user, SponsorCodes $codes): RedirectResponse
    {
        $input = $request->validate(['code' => ['required', 'string', 'min:4', 'max:40']])['code'];
        $code = $codes->redeem($user, is_string($input) ? $input : '');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('billing.codes.redeemed', ['sponsor' => $code->sponsor->name, 'product' => $code->product->name])]);

        return back();
    }

    /** Cards, cancelling and invoices at the gateway's own portal. */
    public function portal(Request $request, #[CurrentUser] User $user, Gateways $gateways): RedirectResponse|HttpResponse
    {
        $url = $gateways->current()?->portal($user, route('billing.index'));

        if ($url === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('billing.noPortal')]);

            return back();
        }

        return Inertia::location($url);
    }
}
