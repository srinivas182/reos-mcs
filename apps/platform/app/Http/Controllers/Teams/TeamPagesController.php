<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Teams\LeadRouting;
use App\Teams\SplitSheets;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Team pages on the web (A17.3): the team leader's lead routing and split sheets, and the agent's team leads to claim and sheets to sign. */
final class TeamPagesController extends Controller
{
    public function leader(#[CurrentUser] User $user, LeadRouting $routing, SplitSheets $splits): Response
    {
        return Inertia::render('team/routing', ['routing' => $routing->board($user), 'splits' => $splits->forLeader($user)]);
    }

    public function agent(#[CurrentUser] User $user, LeadRouting $routing, SplitSheets $splits): Response
    {
        return Inertia::render('team/leads', ['offered' => $routing->offeredTo($user), 'splits' => $splits->mine($user)]);
    }
}
