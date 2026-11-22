<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Privacy\Consents;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * A privacy switch changed by REOS on someone's behalf (A6.3; for example
 * "stop marketing email", asked for by email or phone). Only the switches;
 * the person's own consents for one thing stay theirs. Recorded as "by an
 * admin" with the reason, and audited.
 */
final class PersonConsentController extends Controller
{
    public function update(Request $request, User $person, ConsentPurpose $purpose, Consents $consents, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'granted' => ['required', 'boolean'],
            'note' => ['required', 'string', 'min:5', 'max:300'],
        ]);

        if (! $purpose->switchable()) {
            throw ValidationException::withMessages(['purpose' => __('consents.errors.notSwitch')]);
        }

        $note = is_string($data['note']) ? $data['note'] : '';

        $request->boolean('granted')
            ? $consents->grant($person, $purpose, ConsentMethod::Admin, null, $actor, $note)
            : $consents->withdraw($person, $purpose, ConsentMethod::Admin, null, $actor, $note);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('consents.savedFor', ['name' => $person->name ?? $person->email ?? ''])]);

        return back();
    }
}
