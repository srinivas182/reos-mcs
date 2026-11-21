<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authorization\Role;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Calendar\CalendarView;
use App\Support\PersonTimezone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Each role's dashboard (web journey screens 4, 12, 24, 30, 42, 49, 54, 58).
 * Agents (screen 12) see today's calendar (B4.1); the other cards arrive in
 * their roles' sprints.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, ActiveRole $roles, CalendarView $calendar): Response
    {
        $agent = $roles->for($user)?->role === Role::Agent;
        [$from, $to] = PersonTimezone::today($request);

        return Inertia::render('dashboard', [
            /** Today, in the person's time zone (agents only). */
            'today' => $agent ? [...$calendar->window($user, $from, $to), 'date' => $from->toDateString(), 'timezone' => $from->tzName] : null,
        ]);
    }
}
