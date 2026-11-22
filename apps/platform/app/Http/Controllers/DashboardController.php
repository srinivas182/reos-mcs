<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authorization\Role;
use App\Models\Licence;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Calendar\CalendarView;
use App\Services\StateRules\CeRequirements;
use App\Services\States\PersonStates;
use App\Support\PersonTimezone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Each role's dashboard (web journey screens 4, 12, 24, 30, 42, 49, 54, 58).
 * Agents (screen 12) see today's calendar (B4.1) and their CE hours (A5.2);
 * the other cards arrive in their roles' sprints.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, ActiveRole $roles, CalendarView $calendar, PersonStates $states, CeRequirements $requirements): Response
    {
        $agent = $roles->for($user)?->role === Role::Agent;
        [$from, $to] = PersonTimezone::today($request);

        return Inertia::render('dashboard', [
            /** Today, in the person's time zone (agents only). */
            'today' => $agent ? [...$calendar->window($user, $from, $to), 'date' => $from->toDateString(), 'timezone' => $from->tzName] : null,
            /** CE hours for the licence in the state in use (A5.2, the dashboard's CE tile). */
            'ce' => $agent ? $this->ce($user, $states, $requirements) : null,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function ce(User $user, PersonStates $states, CeRequirements $requirements): ?array
    {
        $licence = Licence::query()->where('user_id', $user->id)->where('state_code', $states->current($user))->first();

        if ($licence === null || ! $licence->status->counts()) {
            return null;
        }

        $ce = $requirements->for($licence);

        return $ce === null ? null : [...$ce, 'state_name' => $licence->state->name];
    }
}
