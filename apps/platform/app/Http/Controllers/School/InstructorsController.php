<?php

declare(strict_types=1);

namespace App\Http\Controllers\School;

use App\Authorization\Role;
use App\Content\Instructors\SchoolInstructors;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Tenancy\Invitations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Instructors (school admin web screen 52, A7.1b): approvals, courses, avatar
 * consent, rating, price and status; Invite, Edit (the courses they teach)
 * and Revoke with Undo. Withdrawn avatar consent shows the date its lessons
 * must be unpublished or re-rendered by.
 */
final class InstructorsController extends Controller
{
    public function index(#[CurrentUser] User $user, ActiveRole $roles, SchoolInstructors $instructors, Invitations $invitations): Response
    {
        $school = $this->school($user, $roles);

        return Inertia::render('school/instructors', [
            'school' => ['name' => $school->name],
            'instructors' => $instructors->list($school),
            'courses' => Course::query()->where('organisation_id', $school->id)->orderBy('title')->get(['id', 'title'])
                ->map(static fn (Course $c): array => ['id' => $c->id, 'title' => $c->title])->all(),
            'invite' => $invitations->options($school),
        ]);
    }

    public function courses(Request $request, User $instructor, #[CurrentUser] User $user, ActiveRole $roles, SchoolInstructors $instructors): RedirectResponse
    {
        $courses = $request->validate(['courses' => ['present', 'array', 'max:200'], 'courses.*' => ['integer']])['courses'];
        $instructors->assign($this->school($user, $roles), $instructor, array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($courses) ? $courses : [])), $user);

        return back()->with('success', __('instructors.saved'));
    }

    public function revoke(User $instructor, #[CurrentUser] User $user, ActiveRole $roles, SchoolInstructors $instructors): RedirectResponse
    {
        $instructors->revoke($this->school($user, $roles), $instructor, $user);

        return back();
    }

    public function restore(User $instructor, #[CurrentUser] User $user, ActiveRole $roles, SchoolInstructors $instructors): RedirectResponse
    {
        $instructors->restore($this->school($user, $roles), $instructor, $user);

        return back();
    }

    private function school(User $user, ActiveRole $roles): Organisation
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && $membership->role === Role::SchoolAdmin && $membership->organisation->type === OrganisationType::School, 404);

        return $membership->organisation;
    }
}
