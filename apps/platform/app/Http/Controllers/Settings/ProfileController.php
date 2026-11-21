<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Identity\AccountDeletion;
use App\Services\Privacy\PrivacyRequests;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, PrivacyRequests $privacy): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            // "Your data" (A4.3b, TDPSA): the person's own requests.
            'privacyRequests' => $user instanceof User ? PrivacyRequest::query()->where('user_id', $user->id)->latest('id')->limit(10)->get()
                ->map(static fn (PrivacyRequest $r): array => [
                    'id' => $r->id,
                    'kind' => $r->kind,
                    'status' => $r->status,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'due_at' => $r->due_at->toIso8601String(),
                    'response' => $r->response,
                    'download' => $r->export_path !== null && $r->completed_at !== null && $r->completed_at->addDays(PrivacyRequests::LINK_DAYS)->isFuture()
                        ? $privacy->downloadLink($r) : null,
                ])->all() : [],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Ask to delete the account: signed out everywhere now, deleted after
     * 30 days unless the person signs in again before then.
     */
    public function destroy(ProfileDeleteRequest $request, #[CurrentUser] User $user, AccountDeletion $deletion): RedirectResponse
    {
        $deletion->request($user);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
