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
use App\Sessions\SessionBookings;
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
            // The most its instructors may charge for 1-on-1 sessions (A10.2d, decision 3).
            'session_limits' => SessionBookings::limitsOf($school),
        ]);
    }

    public function courses(Request $request, User $instructor, #[CurrentUser] User $user, ActiveRole $roles, SchoolInstructors $instructors): RedirectResponse
    {
        $courses = $request->validate(['courses' => ['present', 'array', 'max:200'], 'courses.*' => ['integer']])['courses'];
        $instructors->assign($this->school($user, $roles), $instructor, array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($courses) ? $courses : [])), $user);

        return back()->with('success', __('instructors.saved'));
    }

    public function twins(Request $request, User $instructor, #[CurrentUser] User $user, ActiveRole $roles, SchoolInstructors $instructors): RedirectResponse
    {
        $data = $request->validate([
            'heygen' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'synthesia' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ]);
        $instructors->recordTwins($this->school($user, $roles), $instructor, [
            'heygen' => is_string($data['heygen'] ?? null) ? $data['heygen'] : null,
            'synthesia' => is_string($data['synthesia'] ?? null) ? $data['synthesia'] : null,
        ], $user);

        return back()->with('success', __('instructors.saved'));
    }

    /** The school's limit on 1-on-1 session prices (A10.2d): prices above it come down at once. */
    public function sessionLimits(Request $request, #[CurrentUser] User $user, ActiveRole $roles, SessionBookings $bookings): RedirectResponse
    {
        // Cents; empty means no limit.
        $data = $request->validate([
            'max_30_cents' => ['present', 'nullable', 'integer', 'min:0', 'max:100000'],
            'max_60_cents' => ['present', 'nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $cents = static fn (mixed $v): ?int => is_numeric($v) ? (int) $v : null;
        $lowered = $bookings->setLimits($this->school($user, $roles), $cents($data['max_30_cents'] ?? null), $cents($data['max_60_cents'] ?? null), $user);

        return back()->with('success', __($lowered === 1 ? 'instructors.sessionLimits.saved_one' : 'instructors.sessionLimits.saved_other', ['count' => $lowered]));
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
