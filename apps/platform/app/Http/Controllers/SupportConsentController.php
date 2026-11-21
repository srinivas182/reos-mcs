<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SupportAccessGrant;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Tenancy\SupportAccess;
use App\Tenancy\SupportAccessStatus;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant owner's side of support sign-in (A4.1c): see who asks and
 * why, then approve, decline, or end access that's under way. Runs in
 * platform scope (the owner may be working in another of their roles), so
 * it checks ownership itself.
 */
final class SupportConsentController extends Controller
{
    public function show(int $grant, #[CurrentUser] User $user, SupportAccess $support): Response
    {
        $model = $this->ownedGrant($grant, $user, $support);

        return Inertia::render('support-access/show', [
            'grant' => [
                'id' => $model->id,
                'tenant' => $model->organisation->name,
                'requester' => $model->requester->name,
                'reason' => $model->reason,
                'status' => $model->status->value,
                'requested_at' => $model->created_at?->toIso8601String(),
                'ends_at' => $model->ends_at?->toIso8601String(),
                'decided_by' => $model->decider?->name,
                'minutes' => SupportAccess::MINUTES,
                'answer_hours' => SupportAccess::ANSWER_HOURS,
            ],
            'twoStep' => ActiveRole::hasTwoStep($user),
        ]);
    }

    /** Letting someone in is an admin act, so it needs two-step; saying no never does. */
    public function approve(int $grant, #[CurrentUser] User $user, SupportAccess $support): RedirectResponse
    {
        $model = $this->ownedGrant($grant, $user, $support);

        if (! ActiveRole::hasTwoStep($user)) {
            throw ValidationException::withMessages(['grant' => __('support.errors.twoStep')]);
        }

        $support->answer($model, $user, true);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('support.consent.approved')]);

        return back();
    }

    public function decline(int $grant, #[CurrentUser] User $user, SupportAccess $support): RedirectResponse
    {
        $support->answer($this->ownedGrant($grant, $user, $support), $user, false);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('support.consent.declined')]);

        return back();
    }

    /** The owner ends access, whether it has started or not. */
    public function revoke(int $grant, #[CurrentUser] User $user, SupportAccess $support): RedirectResponse
    {
        $support->end($this->ownedGrant($grant, $user, $support), SupportAccessStatus::Revoked, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('support.consent.revoked')]);

        return back();
    }

    private function ownedGrant(int $id, User $user, SupportAccess $support): SupportAccessGrant
    {
        $grant = SupportAccessGrant::query()->with(['organisation', 'requester', 'decider'])->find($id);

        // Not found and not yours look the same.
        abort_if($grant === null || ! $support->isOwner($user, $grant->organisation), 404);

        return $support->settle($grant);
    }
}
