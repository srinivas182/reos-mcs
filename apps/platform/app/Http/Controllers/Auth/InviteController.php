<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Tenancy\Invitations;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Accepting an invitation (A4.3a): the link or the code leads here, on the
 * tenant's own address and brand. A signed-in person who is the one invited
 * joins with one tap; anyone else signs in or creates the account.
 */
final class InviteController extends Controller
{
    use PasswordValidationRules;

    public function show(string $token, Request $request, Invitations $invitations): SymfonyResponse
    {
        $invitation = $invitations->findByToken($token);
        $user = $request->user();

        if ($invitation === null) {
            return Inertia::render('auth/invite', ['state' => 'invalid', 'token' => null, 'invite' => null, 'account' => null, 'signedInAs' => null])
                ->toResponse($request)->setStatusCode(404);
        }

        return Inertia::render('auth/invite', [
            'state' => $invitation->state() === Invitation::PENDING ? 'open' : $invitation->state(),
            'token' => $token,
            'invite' => [
                'tenant' => $invitation->organisation->name,
                'type' => $invitation->organisation->type->value,
                'team' => $invitation->team?->name,
                'role' => $invitation->role->value,
                'inviter' => $invitation->inviter?->name,
                'contact' => $invitation->email ?? PhoneNumber::mask((string) $invitation->phone),
                'channel' => $invitation->email !== null ? 'email' : 'text',
                'name' => $invitation->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
            'account' => match (true) {
                $user instanceof User && $invitations->isFor($invitation, $user) => 'match',
                $user instanceof User => 'other',
                $invitations->existingAccount($invitation) !== null => 'existing',
                default => 'new',
            },
            'signedInAs' => $user instanceof User ? ($user->email ?? $user->name) : null,
        ])->toResponse($request);
    }

    public function accept(string $token, Request $request, Invitations $invitations, ActiveRole $activeRole): RedirectResponse
    {
        $invitation = $invitations->findByToken($token);
        abort_if($invitation === null, 404);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $invitations->accept($invitation, $user);

        return $this->welcome($user, $invitation, $activeRole);
    }

    /** A new account for the person invited, then straight in. */
    public function register(string $token, Request $request, Invitations $invitations, ActiveRole $activeRole): RedirectResponse
    {
        $invitation = $invitations->findByToken($token);
        abort_if($invitation === null, 404);
        abort_if($request->user() !== null, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // One password field with show/hide, so no confirmation field.
            'password' => $invitation->email !== null ? array_values(array_filter($this->passwordRules(), static fn (mixed $rule): bool => $rule !== 'confirmed')) : ['prohibited'],
        ]);

        $user = $invitations->register($invitation, trim((string) $validated['name']), isset($validated['password']) && is_string($validated['password']) ? $validated['password'] : null);
        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return $this->welcome($user, $invitation, $activeRole);
    }

    /** "I have an account": sign in, then back here to join. */
    public function signIn(string $token, Request $request): RedirectResponse
    {
        $request->session()->put('url.intended', $request->getSchemeAndHttpHost().'/invite/'.$token);

        return redirect()->route('login');
    }

    /** "Have a code?": the 8-character code from a text or email. */
    public function join(): Response
    {
        return Inertia::render('auth/join');
    }

    public function findCode(Request $request, Invitations $invitations): SymfonyResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $invitation = $invitations->findByCode($request->string('code')->toString());

        if ($invitation === null) {
            return back()->withErrors(['code' => __('invites.errors.code')]);
        }

        // The invite lives on the tenant's own address: a full page visit.
        return Inertia::location($invitations->link($invitation));
    }

    private function welcome(User $user, Invitation $invitation, ActiveRole $activeRole): RedirectResponse
    {
        // Straight into the role just joined (the role switcher lists the others).
        $activeRole->choose($user, $invitation->role, $invitation->target()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('invites.joined', ['tenant' => $invitation->organisation->name])]);

        return redirect()->route('dashboard');
    }
}
