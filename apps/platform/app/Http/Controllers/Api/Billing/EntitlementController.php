<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Billing\Entitlements;
use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\User;
use App\Services\States\PersonStates;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * What I may use (B5.1). The phone asks on start and whenever it comes back
 * to the front, so a purchase on the web unlocks the app within seconds.
 */
final class EntitlementController extends Controller
{
    /**
     * My entitlements.
     *
     * `features` lists what's active in my current state; `active` is false
     * when I have nothing paid or sponsored (the app shows the Membership
     * screen).
     *
     * @response array{active: bool, state: string, features: list<string>, data: list<array{feature: string, source: 'order'|'subscription'|'sponsor'|'admin', state: string|null, ends_at: string|null}>}
     */
    public function index(#[CurrentUser] User $user, Entitlements $entitlements, PersonStates $states): JsonResponse
    {
        $state = $states->current($user);
        $features = $entitlements->features($user, $state);

        return response()->json([
            'active' => $features !== [],
            'state' => $state,
            'features' => $features,
            'data' => Entitlement::query()->active()->where('user_id', $user->id)->orderBy('feature')->get()
                ->map(static fn (Entitlement $e): array => ['feature' => $e->feature, 'source' => $e->source->value, 'state' => $e->state_code, 'ends_at' => $e->ends_at?->toIso8601String()])
                ->values()->all(),
        ]);
    }
}
