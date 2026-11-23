<?php

declare(strict_types=1);

namespace App\Http\Controllers\School;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\ReportShare;
use App\Models\StudyGroup;
use App\Models\User;
use App\Practice\StudyGroups;
use App\Services\Authorization\ActiveRole;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Students (school admin web; A9.2e): the weekly progress reports
 * candidates have shared with the school, newest first. A school sees a
 * candidate's report only once the candidate shares it. And (A9.3c) the
 * school's class study groups, with an invite link each.
 */
final class StudentsController extends Controller
{
    public function index(#[CurrentUser] User $user, ActiveRole $roles): Response
    {
        $school = $this->school($user, $roles);

        $shares = ReportShare::query()->where('organisation_id', $school->id)->whereNull('revoked_at')->with('user:id,name')->latest('week_start')->latest('id')->limit(200)->get();

        return Inertia::render('school/students', [
            'reports' => $shares->map(static fn (ReportShare $s): array => [
                'id' => $s->id, 'name' => (string) $s->user->name, 'week_start' => $s->week_start->toDateString(), 'report' => $s->report, 'shared_at' => $s->updated_at?->toIso8601String(),
            ])->values()->all(),
            // Class study groups (A9.3c): how many joined, never their scores.
            'groups' => StudyGroup::query()->where('organisation_id', $school->id)->withCount('members')->latest('id')->get()
                ->map(static fn (StudyGroup $g): array => ['id' => $g->id, 'name' => $g->name, 'members' => (int) $g->members_count, 'max' => StudyGroups::MAX_MEMBERS, 'link' => route('groups.show', $g->uuid)])->values()->all(),
        ]);
    }

    /** A study group for a class (A9.3c): candidates join by its invite link. */
    public function storeGroup(Request $request, #[CurrentUser] User $user, ActiveRole $roles, StudyGroups $groups): RedirectResponse
    {
        $school = $this->school($user, $roles);
        $name = $request->validate(['name' => ['required', 'string', 'min:2', 'max:80']])['name'];
        $groups->createForSchool($school, $user, is_string($name) ? $name : '');

        return back();
    }

    private function school(User $user, ActiveRole $roles): Organisation
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && $membership->role === Role::SchoolAdmin && $membership->organisation->type === OrganisationType::School, 404);

        return $membership->organisation;
    }
}
