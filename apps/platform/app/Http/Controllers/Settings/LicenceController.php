<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Licences\AddLicenceRequest;
use App\Http\Requests\Licences\LicenceRoleRequest;
use App\Models\Licence;
use App\Models\User;
use App\Services\Licences\LicenceCheck;
use App\Services\Licences\Licences;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Settings › Account and roles › Licences (web journey screen 23, A5.1): add
 * a state licence, say who you supervise, remove one (with Undo).
 */
final class LicenceController extends Controller
{
    public function store(AddLicenceRequest $request, #[CurrentUser] User $user, Licences $licences): RedirectResponse
    {
        [$licence, $check] = $licences->add($user, $request->state(), $request->number(), $request->type(), $request->role(), $request->askReview());

        Inertia::flash('toast', [
            'type' => $check === LicenceCheck::Verified ? 'success' : 'info',
            'message' => $check === LicenceCheck::Verified
                ? __('licences.added', ['state' => $licence->state->name])
                : __('licences.sentForReview', ['state' => $licence->state->name]),
        ]);

        return back();
    }

    public function update(LicenceRoleRequest $request, #[CurrentUser] User $user, int $licence, Licences $licences): RedirectResponse
    {
        $licences->setRole($this->find($user, $licence), $request->role(), $user);

        return back();
    }

    public function destroy(#[CurrentUser] User $user, int $licence, Licences $licences): RedirectResponse
    {
        $licences->remove($this->find($user, $licence), $user);

        return back();
    }

    public function restore(#[CurrentUser] User $user, int $licence, Licences $licences): RedirectResponse
    {
        $found = $this->find($user, $licence, trashed: true);
        abort_unless($found->trashed(), 404);
        $licences->restore($found, $user);

        return back();
    }

    private function find(User $user, int $id, bool $trashed = false): Licence
    {
        return ($trashed ? Licence::withTrashed() : Licence::query())->where('user_id', $user->id)->findOrFail($id);
    }
}
