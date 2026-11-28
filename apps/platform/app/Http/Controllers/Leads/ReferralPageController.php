<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Leads\Invites;
use App\Leads\Referrals;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Referrals and invites on the web (B14.3b): refer a client, answer referrals, share the invite link. */
final class ReferralPageController extends Controller
{
    public function index(#[CurrentUser] User $user, Referrals $referrals, Invites $invites): Response
    {
        return Inertia::render('referrals/index', ['referrals' => $referrals->list($user), 'invite' => $invites->summary($user)]);
    }
}
