<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Authorization\Role;
use App\Billing\Checkout;
use App\Billing\Gateways\Gateways;
use App\Billing\Gateways\SimulatedGateway;
use App\Billing\OrderStatus;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StartCheckoutRequest;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Price;
use App\Models\SessionBooking;
use App\Models\SessionOffer;
use App\Models\State;
use App\Models\TenantSignup;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Tenancy\TenantSignups;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout on reos.com (web journey screen 3, B5.1): the product and what's
 * included, the order summary with the sponsor discount, and the gateway's
 * own payment fields (card data never touches REOS). Paying shows the
 * receipt and unlocks the web and the app at once.
 */
final class CheckoutController extends Controller
{
    public function show(Request $request, Price $price, #[CurrentUser] User $user, Checkout $checkout, Gateways $gateways): Response
    {
        abort_unless($price->isCurrent() && $price->product->status === ProductStatus::Live, 404);
        $signup = self::signup($request->query('signup'), $user, $price);
        $booking = self::booking($request->query('booking'), $user, $price);
        $quote = $checkout->quote($user, $price);
        $gateway = $gateways->route($price);

        return Inertia::render('billing/checkout', [
            'price' => self::price($price),
            // Paying for a new brokerage or school (A6.1b).
            'signup' => $signup === null ? null : ['uuid' => $signup->uuid, 'name' => $signup->name],
            // Paying for a held 1-on-1 session (A10.2a).
            'booking' => $booking === null ? null : ['uuid' => $booking->uuid, 'instructor' => (string) $booking->instructor->name, 'starts_at' => $booking->starts_at->toIso8601ZuluString()],
            'quote' => ['amount' => $quote['amount'], 'discount' => $quote['discount'], 'sponsor' => $quote['sponsor']?->name, 'charge' => $quote['charge']],
            'gateway' => $gateway === null ? null : [
                'id' => $gateway->id(),
                'simulator' => $gateway instanceof SimulatedGateway,
                // Gateways without built-in tax need the ZIP code first (REOS works out the tax).
                'needs_postal_code' => ! $gateway->calculatesTax(),
                'publishable_key' => $gateway instanceof SimulatedGateway || $gateway->id() !== Gateways::STRIPE ? null : $gateways->publishableKey(),
            ],
        ]);
    }

    /**
     * Starts the order; the page mounts Stripe's payment fields, sends the
     * buyer to the gateway's hosted page (`redirect`), or shows the
     * Simulator, with the answer.
     */
    public function start(StartCheckoutRequest $request, Price $price, #[CurrentUser] User $user, Checkout $checkout, TenantSignups $signups): JsonResponse
    {
        $signup = self::signup($request->signup(), $user, $price);
        $booking = self::booking($request->booking(), $user, $price);
        abort_if($price->product->kind === ProductKind::Session && $booking === null, 422);
        $started = $checkout->start($user, $price, static fn (Order $order): string => route('checkout.complete', $order).'?session_id={CHECKOUT_SESSION_ID}', $request->postalCode());
        $order = $started['order'];

        if ($signup !== null) {
            $signups->attach($signup, $order);
        }

        $booking?->forceFill(['order_id' => $order->id])->save();

        return response()->json([
            'order' => $order->uuid,
            'gateway' => $started['gateway']->id(),
            'client_secret' => $started['session']->clientSecret,
            'redirect' => $started['session']->redirect,
            'simulator' => $started['gateway'] instanceof SimulatedGateway,
            'tax' => $order->tax_cents,
            'total' => $order->chargeCents() + ($order->tax_cents ?? 0),
        ], 201);
    }

    /** The Simulator's "Pay" (never in production). */
    public function simulate(Order $order, #[CurrentUser] User $user, Gateways $gateways): RedirectResponse
    {
        abort_unless($order->user_id === $user->id && $order->environment === 'simulator' && $gateways->driver($order->gateway) instanceof SimulatedGateway, 404);
        SimulatedGateway::pay((string) $order->gateway_session_id);

        return to_route('checkout.complete', $order);
    }

    /** Back from paying: REOS asks the gateway directly and unlocks at once. */
    public function complete(Order $order, #[CurrentUser] User $user, Checkout $checkout, ActiveRole $roles): Response
    {
        abort_unless($order->user_id === $user->id, 404);
        $order = $checkout->confirm($order);
        $signup = TenantSignup::query()->with('organisation')->where('order_id', $order->id)->first();

        // A new brokerage or school: its owner works there from now on.
        if ($signup?->organisation !== null && $signup->status === 'completed') {
            $roles->choose($user, $signup->type === OrganisationType::School ? Role::SchoolAdmin : Role::Broker, $signup->organisation->id);
        }

        return Inertia::render('billing/complete', [
            'order' => [
                'uuid' => $order->uuid,
                'gateway' => $order->gateway,
                'status' => $order->status->value,
                'product' => $order->product->name,
                'state' => self::stateName($order->state_code),
                'amount' => $order->amount_cents,
                'discount' => $order->discount_cents,
                'sponsor' => $order->sponsor?->name,
                'tax' => $order->tax_cents,
                'total' => $order->total_cents,
                'receipt_url' => $order->receipt_url,
                'paid_at' => $order->paid_at?->toIso8601String(),
            ],
            'tenant' => $signup?->organisation === null ? null : ['name' => $signup->organisation->name, 'type' => $signup->type->value],
            'unlocked' => $order->status === OrderStatus::Paid
                ? Entitlement::query()->active()->where('user_id', $user->id)->pluck('feature')->unique()->values()->all()
                : [],
        ]);
    }

    /** A pending sign-up of this person's, for this price. */
    private static function signup(mixed $uuid, User $user, Price $price): ?TenantSignup
    {
        return is_string($uuid) && $uuid !== '' ? TenantSignup::query()->where('uuid', $uuid)->where('user_id', $user->id)
            ->where('price_id', $price->id)->where('status', 'pending')->first() : null;
    }

    public static function stateName(string $code): string
    {
        $name = State::query()->whereKey($code)->value('name');

        return is_string($name) ? $name : $code;
    }

    /** @return array<string, mixed> */
    public static function price(Price $price): array
    {
        return [
            'id' => $price->id,
            'product' => $price->product->name,
            'kind' => $price->product->kind->value,
            'description' => $price->product->description,
            'includes' => $price->product->includes ?? [],
            'state' => self::stateName($price->state_code),
            'interval' => $price->interval->value,
            'amount' => $price->amount_cents,
        ];
    }

    /** The student's own held booking for this session product, still within its hold (A10.2a). */
    private static function booking(mixed $uuid, User $user, Price $price): ?SessionBooking
    {
        if (! is_string($uuid) || $price->product->kind !== ProductKind::Session) {
            return null;
        }

        $booking = SessionBooking::query()->where('uuid', $uuid)->where('student_id', $user->id)->where('status', 'held')->first();
        $offer = $booking === null ? null : SessionOffer::query()->where('user_id', $booking->instructor_id)->first();

        abort_unless($booking !== null && $offer !== null && $offer->productId($booking->minutes) === $price->product_id && ($booking->held_until?->isFuture() ?? false), 404);

        return $booking;
    }
}
