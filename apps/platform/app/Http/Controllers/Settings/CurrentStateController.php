<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Licences\ChooseStateRequest;
use App\Models\User;
use App\Services\States\PersonStates;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/** The state switcher in the web top bar (A5.1). */
final class CurrentStateController extends Controller
{
    public function update(ChooseStateRequest $request, #[CurrentUser] User $user, PersonStates $states): RedirectResponse
    {
        $states->choose($user, $request->code());

        return back();
    }
}
