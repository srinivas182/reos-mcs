<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActiveRoleRequest;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/** The role switcher in the web app's header; this browser remembers the choice. */
final class ActiveRoleController extends Controller
{
    public function update(ActiveRoleRequest $request, #[CurrentUser] User $user, ActiveRole $active): RedirectResponse
    {
        $active->choose($user, $request->role(), $request->organisationId());

        return redirect()->route('dashboard');
    }
}
