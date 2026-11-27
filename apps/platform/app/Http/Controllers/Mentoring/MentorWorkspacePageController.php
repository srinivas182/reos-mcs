<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mentoring;

use App\Http\Controllers\Controller;
use App\Mentoring\MentorWorkspace;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The mentor workspace on the web (A13.1b, ADR 0053; web journey screen
 * 48, mentor screens 22–24): Today and mentees, and a mentee's detail.
 */
final class MentorWorkspacePageController extends Controller
{
    public function index(#[CurrentUser] User $user, MentorWorkspace $workspace): Response
    {
        return Inertia::render('mentoring/index', ['today' => $workspace->today($user), 'mentees' => $workspace->mentees($user)]);
    }

    public function show(int $agent, #[CurrentUser] User $user, MentorWorkspace $workspace): Response
    {
        return Inertia::render('mentoring/show', ['detail' => $workspace->mentee($user, $agent)]);
    }
}
