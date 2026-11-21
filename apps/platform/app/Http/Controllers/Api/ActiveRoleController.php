<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActiveRoleRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Illuminate\Container\Attributes\CurrentUser;

/** The role switcher. */
final class ActiveRoleController extends Controller
{
    /**
     * Switch role.
     *
     * Use another role the person holds; this device remembers it, and
     * signing in again returns to it. Answers 422 with reason
     * `roles.errors.notHeld` for a role the person doesn't hold. Admin roles
     * can be chosen without two-step, but `permissions` stays empty and
     * `two_step.required` is true until it is set up.
     */
    public function __invoke(ActiveRoleRequest $request, #[CurrentUser] User $user, ActiveRole $active): MeResource
    {
        $active->choose($user, $request->role(), $request->organisationId());

        return new MeResource($user->load('socialAccounts'));
    }
}
