<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PersonRoleRequest;
use App\Models\User;
use App\Services\Authorization\Memberships;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/** Give a person a role, or take one away (audited). */
final class PersonRoleController extends Controller
{
    public function store(PersonRoleRequest $request, User $person, #[CurrentUser] User $actor, Memberships $memberships): RedirectResponse
    {
        $memberships->grant($person, $request->role(), $request->organisation(), $actor);

        return back();
    }

    public function destroy(PersonRoleRequest $request, User $person, #[CurrentUser] User $actor, Memberships $memberships): RedirectResponse
    {
        $memberships->revoke($person, $request->role(), $request->organisation(), $actor);

        return back();
    }
}
