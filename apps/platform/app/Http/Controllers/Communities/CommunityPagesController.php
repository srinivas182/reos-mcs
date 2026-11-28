<?php

declare(strict_types=1);

namespace App\Http\Controllers\Communities;

use App\Communities\Communities;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Communities on the web (B17.2, provider screens 16–18): the owner's community and results, a member's view, and the invite. */
final class CommunityPagesController extends Controller
{
    public function own(#[CurrentUser] User $user, Communities $communities): Response|RedirectResponse
    {
        try {
            $community = $communities->forOwner($user);
        } catch (ValidationException) {
            // Listed providers: the plan comes first.
            return redirect()->route('billing.index');
        }

        return Inertia::render('community/manage', ['community' => $communities->view($user, $community->uuid), 'results' => $communities->results($user), 'canCharge' => $communities->canCharge($user)]);
    }

    /** The QR code to join, for printing at events (provider screen 16). */
    public function qr(#[CurrentUser] User $user, Communities $communities): \Illuminate\Http\Response
    {
        return response($communities->qr($user), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=300']);
    }

    public function results(#[CurrentUser] User $user, Communities $communities): Response|RedirectResponse
    {
        try {
            return Inertia::render('community/results', ['results' => $communities->results($user)]);
        } catch (ValidationException) {
            return redirect()->route('billing.index');
        }
    }

    public function mine(#[CurrentUser] User $user, Communities $communities): Response
    {
        return Inertia::render('community/mine', ['communities' => $communities->mine($user)]);
    }

    public function show(string $community, #[CurrentUser] User $user, Communities $communities): Response
    {
        return Inertia::render('community/show', ['community' => $communities->view($user, $community)]);
    }

    public function invite(string $code, #[CurrentUser] User $user, Communities $communities): Response
    {
        return Inertia::render('community/join', ['invite' => $communities->preview($user, $code), 'code' => $code]);
    }
}
