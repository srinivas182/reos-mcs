<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Authorization\Role;
use App\Billing\Buyer;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use App\Brokerage\BrokerageStaff;
use App\Enums\OrganisationType;
use App\Models\BrokerVerification;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\TenantSignup;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Self-serve sign-up for brokerages and schools (A6.1b, the Sprint 6 gate).
 * The owner names the organisation and its states, picks a plan REOS sells
 * to that kind of tenant, and pays on the web (B5.1 checkout). When the
 * payment lands the tenant is created with the plan's seats, and the owner
 * becomes its broker or school admin. Associations join with the REOS team
 * (screen 59), as the public site says.
 *
 * A brokerage starts with its designated broker's TREC check (A15.1a,
 * ADR 0069): the sign-up carries the verification, and the tenant keeps
 * the TREC number, so one brokerage is on REOS once.
 */
final readonly class TenantSignups
{
    /** Who can sign up on their own, and what they buy and become. */
    public const TYPES = [
        'brokerage' => ['type' => OrganisationType::Brokerage, 'buyer' => Buyer::Broker, 'role' => Role::Broker],
        'school' => ['type' => OrganisationType::School, 'buyer' => Buyer::School, 'role' => Role::SchoolAdmin],
    ];

    public function __construct(private Tenants $tenants, private Memberships $memberships, private AuditLogger $audit, private Tenancy $tenancy) {}

    /**
     * The plans this kind of tenant can buy in a state.
     *
     * @return Collection<int, Price>
     */
    public function plans(string $kind, string $state): Collection
    {
        $buyer = self::TYPES[$kind]['buyer'] ?? null;

        return $buyer === null ? collect() : Price::query()->with('product')->current()->where('state_code', $state)
            ->whereHas('product', static fn ($q) => $q->where('buyer', $buyer)->where('status', ProductStatus::Live)->whereIn('kind', [ProductKind::Plan, ProductKind::SeatPack]))
            ->orderBy('amount_cents')->get();
    }

    /** @param  list<string>  $states */
    public function start(User $owner, string $kind, string $name, array $states, Price $price, ?BrokerVerification $verification = null): TenantSignup
    {
        if (! $this->plans($kind, $price->state_code)->contains('id', $price->id) || ! in_array($price->state_code, $states, true)) {
            throw ValidationException::withMessages(['price' => __('signup.errors.plan')]);
        }

        if ($kind === 'brokerage') {
            $this->checkVerification($owner, $verification, $states);
        }

        return TenantSignup::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'type' => self::TYPES[$kind]['type'],
            'name' => $name,
            'states' => array_values(array_unique($states)),
            'price_id' => $price->id,
            'broker_verification_id' => $verification?->id,
        ]);
    }

    /**
     * A Texas brokerage needs its designated broker's own, fresh, unused
     * TREC check, for a brokerage not already on REOS.
     *
     * @param  list<string>  $states
     */
    private function checkVerification(User $owner, ?BrokerVerification $verification, array $states): void
    {
        if ($verification === null || $verification->user_id !== $owner->id || $verification->used_at !== null || $verification->created_at?->lessThan(now()->subDay()) === true) {
            throw ValidationException::withMessages(['verification' => __('brokerage.verify.needed')]);
        }

        if (! in_array('TX', $states, true)) {
            throw ValidationException::withMessages(['states' => __('brokerage.verify.texas')]);
        }

        if (Organisation::query()->where('trec_number', $verification->brokerage_number)->exists()) {
            throw ValidationException::withMessages(['verification' => __('brokerage.verify.claimed', ['name' => $verification->brokerage_name])]);
        }
    }

    /** Links the checkout's order to the sign-up it pays for. */
    public function attach(TenantSignup $signup, Order $order): void
    {
        $signup->forceFill(['order_id' => $order->id])->save();
    }

    /** The order is paid: create the tenant and make the owner its admin (once). */
    public function complete(Order $order): ?TenantSignup
    {
        // Creating a tenant writes rows for it, outside the buyer's own scope (row-level security).
        return $this->tenancy->asPlatform(fn (): ?TenantSignup => DB::transaction(function () use ($order): ?TenantSignup {
            $signup = TenantSignup::query()->where('order_id', $order->id)->where('status', 'pending')->lockForUpdate()->first();

            if ($signup === null) {
                return null;
            }

            $owner = $signup->user;
            $kind = $signup->type === OrganisationType::School ? 'school' : 'brokerage';
            $seats = $order->product->seats;
            $tenant = $this->tenants->create([
                'type' => $signup->type,
                'name' => $signup->name,
                'states' => $signup->states,
                'plan' => TenantPlan::defaultFor($signup->type)->value,
                ...($seats === null ? [] : ['seats' => $seats]),
            ], $owner);
            $this->memberships->grant($owner, self::TYPES[$kind]['role'], $tenant, $owner);

            // The TREC brokerage this tenant stands for (A15.1a): claimed once.
            $verification = $signup->broker_verification_id === null ? null : BrokerVerification::query()->find($signup->broker_verification_id);

            if ($verification !== null) {
                $tenant->forceFill(['trec_number' => $verification->brokerage_number])->save();
                // Its designated broker (A15.2): the person TREC lists, who set it up.
                app(BrokerageStaff::class)->makeDesignated($tenant, $owner);
                $verification->forceFill(['used_at' => now()])->save();
            }

            $signup->forceFill(['status' => 'completed', 'created_organisation_id' => $tenant->id])->save();
            $this->audit->record('tenant.signed_up', 'tenant', (string) $tenant->id, ['order' => $order->uuid, 'product' => $order->product->key], $owner, $tenant->id);

            return $signup;
        }));
    }
}
