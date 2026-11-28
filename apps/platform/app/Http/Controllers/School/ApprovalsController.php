<?php

declare(strict_types=1);

namespace App\Http\Controllers\School;

use App\Authorization\Role;
use App\Content\Approvals\CourseApprovals;
use App\Content\Delivery;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\ApprovalRequest;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\Organisation;
use App\Models\State;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Course catalogue and state approvals (school admin web screen 50, A7.1):
 * every approval of the school's courses with type, state, number, hours,
 * expiry, delivery and status; Add approval, Add state and Renew. No CE or
 * qualifying label without a recorded approval.
 */
final class ApprovalsController extends Controller
{
    public function index(#[CurrentUser] User $user, ActiveRole $roles, CourseApprovals $approvals): Response
    {
        $school = $this->school($user, $roles);
        $today = CarbonImmutable::today();
        $courses = Course::query()->where('organisation_id', $school->id)->orderBy('title')->get(['id', 'title', 'type', 'delivery', 'hours', 'states', 'status']);

        return Inertia::render('school/approvals', [
            'school' => ['name' => $school->name],
            'approvals' => $approvals->forSchool($school)->map(static fn (CourseApproval $a): array => self::row($a, $today))->all(),
            'courses' => $courses->map(static fn (Course $c): array => [
                'id' => $c->id, 'title' => $c->title, 'type' => $c->type->value, 'delivery' => $c->delivery->value,
                'hours' => $c->hours === null ? null : (float) $c->hours, 'states' => $c->states ?? [], 'needs_approval' => $c->type->needsApproval(),
            ])->all(),
            'states' => State::query()->orderBy('name')->get(['code', 'name'])->map(static fn (State $s): array => ['code' => $s->code, 'name' => $s->name])->all(),
            'deliveries' => Delivery::values(),
            'defaults' => collect(['TX'])->mapWithKeys(static fn (string $code): array => [$code => $approvals->defaults($code)])->all(),
        ]);
    }

    /** The seat-time defaults for a state, for the Add dialog when another state is picked. */
    public function defaults(Request $request, #[CurrentUser] User $user, ActiveRole $roles, CourseApprovals $approvals): JsonResponse
    {
        $this->school($user, $roles);
        $state = $request->validate(['state' => ['required', 'string', 'size:2', Rule::exists('states', 'code')]])['state'];

        return response()->json($approvals->defaults(is_string($state) ? $state : 'TX'));
    }

    public function store(ApprovalRequest $request, #[CurrentUser] User $user, ActiveRole $roles, CourseApprovals $approvals): RedirectResponse
    {
        $school = $this->school($user, $roles);
        $course = Course::query()->where('organisation_id', $school->id)->findOrFail($request->integer('course_id'));
        $approvals->add($course, $request->safe()->except('course_id'), $user);

        return back()->with('success', __('approvals.added'));
    }

    public function update(ApprovalRequest $request, CourseApproval $approval, #[CurrentUser] User $user, ActiveRole $roles, CourseApprovals $approvals): RedirectResponse
    {
        $this->ownApproval($approval, $this->school($user, $roles));
        $approvals->update($approval, $request->validated(), $user);

        return back()->with('success', __('approvals.saved'));
    }

    public function status(Request $request, CourseApproval $approval, #[CurrentUser] User $user, ActiveRole $roles, CourseApprovals $approvals): RedirectResponse
    {
        $this->ownApproval($approval, $this->school($user, $roles));
        $status = $request->validate(['status' => ['required', Rule::in([CourseApproval::PENDING, CourseApproval::APPROVED, CourseApproval::WITHDRAWN])]])['status'];
        $approvals->setStatus($approval, is_string($status) ? $status : '', $user);

        return back();
    }

    /** @return array<string, mixed> */
    private static function row(CourseApproval $a, CarbonImmutable $today): array
    {
        return [
            'id' => $a->id,
            'course' => ['id' => $a->course->id, 'title' => $a->course->title, 'type' => $a->course->type->value, 'delivery' => $a->course->delivery->value],
            'state_code' => $a->state_code,
            'delivery' => $a->delivery->value,
            'number' => $a->number,
            'hours' => $a->hours === null ? null : (float) $a->hours,
            'approved_on' => $a->approved_on?->toDateString(),
            'expires_on' => $a->expires_on?->toDateString(),
            'days_left' => $a->daysLeft($today),
            'status' => $a->status,
            'shown_status' => $a->shownStatus($today),
            // Gives the course its label: approved, in force, and for the delivery the course uses.
            'labels_course' => $a->valid($today) && $a->delivery === $a->course->delivery,
            'max_hours_per_day' => $a->max_hours_per_day === null ? null : (float) $a->max_hours_per_day,
            'checkin_minutes' => $a->checkin_minutes,
            'presence_percent' => $a->presence_percent,
            'makeup_min_percent' => $a->makeup_min_percent,
            'makeup_days' => $a->makeup_days,
            'records_live' => $a->records_live,
            'audio_allowed' => $a->audio_allowed,
            'note' => $a->note,
        ];
    }

    private function ownApproval(CourseApproval $approval, Organisation $school): void
    {
        abort_unless($approval->course->organisation_id === $school->id, 404);
    }

    private function school(User $user, ActiveRole $roles): Organisation
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && $membership->role === Role::SchoolAdmin && $membership->organisation->type === OrganisationType::School, 404);

        return $membership->organisation;
    }
}
