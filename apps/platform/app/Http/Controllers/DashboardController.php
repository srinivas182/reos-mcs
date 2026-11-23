<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authorization\Role;
use App\Content\Approvals\CourseApprovals;
use App\Content\Builder\InstructorDashboard;
use App\Enums\OrganisationType;
use App\Models\Licence;
use App\Models\User;
use App\Monitoring\PlatformOverview;
use App\Practice\CandidateToday;
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
 * school admins (screen 49) their course approvals (A7.1); instructors
 * (screen 42) their teaching summary (A7.2); the other cards
 * arrive in their roles' sprints.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, ActiveRole $roles, CalendarView $calendar, PersonStates $states, CeRequirements $requirements, PlatformOverview $overview, CourseApprovals $approvals, InstructorDashboard $teaching, CandidateToday $study): Response
    {
        // The REOS super admin's home is the platform overview (screen 58, A6.1).
        if ($roles->for($user)?->role === Role::SuperAdmin) {
            $range = $request->validate(['range' => ['nullable', 'in:24h,30d']])['range'] ?? '24h';

            return Inertia::render('admin/overview', ['overview' => $overview->summary(is_string($range) ? $range : '24h')]);
        }

        $membership = $roles->for($user);
        $agent = $membership?->role === Role::Agent;
        $atSchool = $membership !== null && $membership->organisation->type === OrganisationType::School;
        $school = $atSchool && $membership->role === Role::SchoolAdmin ? $membership->organisation : null;
        $teaches = $atSchool && $membership->role === Role::Instructor ? $membership->organisation : null;
        [$from, $to] = PersonTimezone::today($request);

        return Inertia::render('dashboard', [
            /** Today, in the person's time zone (agents only). */
            'today' => $agent ? [...$calendar->window($user, $from, $to), 'date' => $from->toDateString(), 'timezone' => $from->tzName] : null,
            /** CE hours for the licence in the state in use (A5.2, the dashboard's CE tile). */
            'ce' => $agent ? $this->ce($user, $states, $requirements) : null,
            /** Course approvals in force and what needs attention (school admins, screen 49, A7.1). */
            'approvals' => $school === null ? null : $approvals->summary($school),
            /** Readiness, streak, exam date, Continue and today's plan (candidates, screen 4, A8.3). */
            'study' => $membership?->role === Role::Candidate ? $study->for($user) : null,
            /** Reviews, sessions, pass rate, today and the most-missed topics (instructors, screen 42, A7.2). */
            'teaching' => $teaches === null ? null : $teaching->for($user, $teaches),
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
