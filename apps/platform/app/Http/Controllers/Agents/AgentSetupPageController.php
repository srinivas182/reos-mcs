<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agents;

use App\Agents\AgentModes;
use App\Agents\AgentPlans;
use App\Agents\AgentToday;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PersonTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The agent's welcome, goal and flags (screens 4–6), and My goal in Settings (146), on the web (A11.1a). */
final class AgentSetupPageController extends Controller
{
    public function setup(#[CurrentUser] User $user, AgentModes $modes): Response
    {
        return Inertia::render('agent/setup', ['mode' => $modes->show($user)]);
    }

    /** The 90-day plan with this week's scorecard (screens 10 and 110). */
    public function plan(Request $request, #[CurrentUser] User $user, AgentPlans $plans, AgentToday $today): Response
    {
        $modes = app(AgentModes::class);
        $modes->show($user);

        return Inertia::render('agent/plan', ['plan' => $plans->current($user), 'scorecard' => $today->scorecard($user, CarbonImmutable::now(PersonTimezone::of($request)))]);
    }

    public function goal(#[CurrentUser] User $user, AgentModes $modes): Response
    {
        return Inertia::render('settings/goal', ['mode' => $modes->show($user)]);
    }
}
