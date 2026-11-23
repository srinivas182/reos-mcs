<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mentors;

use App\Http\Controllers\Controller;
use App\Licences\Journey\Mentors;
use App\Licences\Journey\SwitchToAgent;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Switching to agent (f03, 20) and Your mentors (f04) on the web (A10.3c). */
final class MentorsPageController extends Controller
{
    public function licence(#[CurrentUser] User $user, SwitchToAgent $switch): Response
    {
        return Inertia::render('licence/switch', ['switch' => $switch->show($user)]);
    }

    public function mentors(#[CurrentUser] User $user, Mentors $mentors): Response
    {
        return Inertia::render('mentors/index', $mentors->for($user));
    }
}
