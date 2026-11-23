<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agents;

use App\Agents\AgentModes;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** The agent's welcome, goal and flags (screens 4–6), and My goal in Settings (146), on the web (A11.1a). */
final class AgentSetupPageController extends Controller
{
    public function setup(#[CurrentUser] User $user, AgentModes $modes): Response
    {
        return Inertia::render('agent/setup', ['mode' => $modes->show($user)]);
    }

    public function goal(#[CurrentUser] User $user, AgentModes $modes): Response
    {
        return Inertia::render('settings/goal', ['mode' => $modes->show($user)]);
    }
}
