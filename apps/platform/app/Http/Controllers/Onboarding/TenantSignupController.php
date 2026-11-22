<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Enums\StateStatus;
use App\Http\Controllers\Controller;
use App\Models\Price;
use App\Models\State;
use App\Models\User;
use App\Services\Tenancy\TenantSignups;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Start your brokerage" and "Start your school" on reos.com (A6.1b, the
 * Sprint 6 gate): name the organisation, pick its states and a plan, then
 * pay on the web; the tenant is ready the moment the payment lands.
 */
final class TenantSignupController extends Controller
{
    public function show(Request $request, string $kind, TenantSignups $signups): Response
    {
        $states = State::query()->where('status', StateStatus::Live)->orderBy('name')->get(['code', 'name']);
        $state = $request->validate(['state' => ['nullable', 'string', 'size:2']])['state'] ?? null;
        $codes = $states->map(static fn (State $s): string => $s->code)->values()->all();
        $state = is_string($state) && in_array(strtoupper($state), $codes, true) ? strtoupper($state) : ($codes[0] ?? 'TX');

        return Inertia::render('public/start', [
            'kind' => $kind,
            'state' => $state,
            'states' => $states->map(static fn (State $s): array => ['code' => $s->code, 'name' => $s->name])->values()->all(),
            'plans' => $signups->plans($kind, $state)->map(static fn (Price $price): array => [
                'id' => $price->id,
                'product' => $price->product->name,
                'description' => $price->product->description,
                'includes' => $price->product->includes ?? [],
                'seats' => $price->product->seats,
                'interval' => $price->interval->value,
                'amount' => $price->amount_cents,
            ])->values()->all(),
        ]);
    }

    public function store(Request $request, string $kind, #[CurrentUser] User $user, TenantSignups $signups): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'states' => ['required', 'array', 'min:1', 'max:10'],
            'states.*' => ['string', 'size:2', Rule::exists('states', 'code')->where('status', StateStatus::Live->value)],
            'price_id' => ['required', 'integer', Rule::exists('prices', 'id')],
        ]);
        $states = array_values(array_filter(is_array($data['states']) ? $data['states'] : [], is_string(...)));
        $price = Price::query()->findOrFail(is_numeric($data['price_id']) ? (int) $data['price_id'] : 0);
        $signup = $signups->start($user, $kind, is_string($data['name']) ? trim($data['name']) : '', $states, $price);

        return to_route('checkout.show', ['price' => $price->id, 'signup' => $signup->uuid]);
    }
}
