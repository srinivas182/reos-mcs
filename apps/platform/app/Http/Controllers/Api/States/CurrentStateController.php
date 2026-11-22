<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\States;

use App\Http\Controllers\Controller;
use App\Http\Requests\Licences\ChooseStateRequest;
use App\Models\User;
use App\Services\States\PersonStates;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * The state switcher (A5.1): the live states you hold a licence in or study
 * for, and the one in use. Content, plans and the CE card follow it.
 */
final class CurrentStateController extends Controller
{
    /**
     * Your states.
     *
     * @return array{current: array{code: string, name: string}, options: list<array{code: string, name: string}>}
     */
    public function show(#[CurrentUser] User $user, PersonStates $states): array
    {
        return $states->switcher($user);
    }

    /**
     * Switch state.
     *
     * @return array{current: array{code: string, name: string}, options: list<array{code: string, name: string}>}
     */
    public function update(ChooseStateRequest $request, #[CurrentUser] User $user, PersonStates $states): array
    {
        $states->choose($user, $request->code());

        return $states->switcher($user);
    }
}
