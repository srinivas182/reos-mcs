<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Brokerage\Oversight;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Brokerage › Oversight on the web (A16.1): agents needing help and deals at risk, and an agent's profile. */
final class OversightPagesController extends Controller
{
    public function index(#[CurrentUser] User $user, Oversight $oversight): Response
    {
        return Inertia::render('organisation/oversight', ['board' => $oversight->board($user)]);
    }

    public function show(int $member, #[CurrentUser] User $user, Oversight $oversight): Response
    {
        return Inertia::render('organisation/oversight-agent', ['profile' => $oversight->profile($user, $member), 'settings' => $oversight->board($user)['settings']]);
    }
}
