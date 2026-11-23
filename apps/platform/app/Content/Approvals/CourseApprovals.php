<?php

declare(strict_types=1);

namespace App\Content\Approvals;

use App\Authorization\Role;
use App\Content\Delivery;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\Organisation;
use App\Models\State;
use App\Models\User;
use App\Notifications\ApprovalExpiring;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Services\StateRules\StateRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Course approvals per state (A7.1, ADR 0025; school admin screen 50).
 *
 * A school records the regulator's approval for each of its CE and
 * qualifying courses, per state and delivery method, with the hours and the
 * seat-time and attendance rules (defaults from the state's rules, screen
 * 62). Only an approved, unexpired approval gives the course its label; the
 * school is reminded 60 and 30 days before one expires, and on expiry the
 * course loses its label in that state. Every change is audited.
 */
final readonly class CourseApprovals
{
    /** REOS's own check-in default for self-paced and avatar lessons (not a regulator rule). */
    public const CHECKIN_MINUTES = 15;

    private const AUDIT_SUBJECT = 'course_approval';

    public function __construct(
        private StateRules $rules,
        private AuditLogger $audit,
        private Memberships $memberships,
    ) {}

    /**
     * A school's approvals, course by course.
     *
     * @return Collection<int, CourseApproval>
     */
    public function forSchool(Organisation $school): Collection
    {
        return CourseApproval::query()
            ->with('course')
            ->whereHas('course', static fn ($q) => $q->where('organisation_id', $school->id))
            ->orderBy('state_code')
            ->get()
            ->sortBy(static fn (CourseApproval $a): string => mb_strtolower($a->course->title).' '.$a->state_code.' '.$a->delivery->value)
            ->values();
    }

    /**
     * The school dashboard's approvals card (screen 49): how many approvals
     * are in force, and what needs attention (expiring within 60 days,
     * expired, or still waiting for the regulator).
     *
     * @return array{courses: int, in_force: int, attention: list<array{id: int, course: string, state_code: string, number: string|null, expires_on: string|null, days_left: int|null, status: string}>}
     */
    public function summary(Organisation $school, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $approvals = $this->forSchool($school);
        $attention = $approvals
            ->filter(static fn (CourseApproval $a): bool => $a->status === CourseApproval::PENDING
                || ($a->status === CourseApproval::APPROVED && $a->expires_on !== null && ($a->daysLeft($today) ?? 0) <= max(CourseApproval::REMIND_DAYS)))
            ->sortBy(static fn (CourseApproval $a): int => $a->daysLeft($today) ?? PHP_INT_MAX)
            ->values()
            ->map(static fn (CourseApproval $a): array => [
                'id' => $a->id, 'course' => $a->course->title, 'state_code' => $a->state_code, 'number' => $a->number,
                'expires_on' => $a->expires_on?->toDateString(), 'days_left' => $a->daysLeft($today), 'status' => $a->shownStatus($today),
            ]);

        return [
            'courses' => Course::query()->where('organisation_id', $school->id)->count(),
            'in_force' => $approvals->filter(static fn (CourseApproval $a): bool => $a->valid($today))->count(),
            'attention' => array_values($attention->all()),
        ];
    }

    /**
     * The seat-time and attendance defaults for a state (its `courses.seat_time` rule).
     *
     * @return array{max_hours_per_day: float|null, checkin_minutes: int, presence_percent: int|null, makeup_min_percent: int|null, makeup_days: int|null}
     */
    public function defaults(string $state, ?CarbonImmutable $on = null): array
    {
        $rule = $this->rules->inForce(strtoupper($state), $on ?? CarbonImmutable::today())['courses.seat_time'] ?? null;
        $value = $rule === null ? [] : $rule->value;
        $number = static fn (string $key): mixed => isset($value[$key]) && is_numeric($value[$key]) ? $value[$key] : null;

        return [
            'max_hours_per_day' => $number('max_hours_per_day') === null ? null : (float) $number('max_hours_per_day'),
            'checkin_minutes' => self::CHECKIN_MINUTES,
            'presence_percent' => $number('presence_percent') === null ? null : (int) $number('presence_percent'),
            'makeup_min_percent' => $number('makeup_min_percent') === null ? null : (int) $number('makeup_min_percent'),
            'makeup_days' => $number('makeup_days') === null ? null : (int) $number('makeup_days'),
        ];
    }

    /**
     * Adds an approval ("Add approval" or "Add state"). A state the course
     * isn't tagged to yet is added to it.
     *
     * @param  array<string, mixed>  $data  state_code, delivery, number, hours, approved_on, expires_on, status, seat-time fields, note
     */
    public function add(Course $course, array $data, User $actor): CourseApproval
    {
        $state = strtoupper(self::string($data['state_code'] ?? ''));
        $delivery = Delivery::from(self::string($data['delivery'] ?? $course->delivery->value));

        if (! State::query()->where('code', $state)->exists()) {
            throw ValidationException::withMessages(['state_code' => __('approvals.errors.state')]);
        }

        if ($course->approvals()->where('state_code', $state)->where('delivery', $delivery->value)->exists()) {
            throw ValidationException::withMessages(['state_code' => __('approvals.errors.duplicate')]);
        }

        $fields = $this->fields($data, $course) + $this->defaults($state);
        self::ensureComplete($fields);

        return DB::transaction(function () use ($course, $state, $delivery, $fields, $actor): CourseApproval {
            $approval = $course->approvals()->create([...$fields, 'state_code' => $state, 'delivery' => $delivery, 'created_by' => $actor->id]);

            if (! in_array($state, $course->states ?? [], true)) {
                $states = [...($course->states ?? []), $state];
                sort($states);
                $course->forceFill(['states' => $states])->save();
            }

            $this->audit->record('course.approval_added', self::AUDIT_SUBJECT, (string) $approval->id, [
                'course_id' => $course->id, 'state' => $state, 'delivery' => $delivery->value, 'number' => $approval->number, 'status' => $approval->status, 'expires_on' => $approval->expires_on?->toDateString(),
            ], $actor, $course->organisation_id);

            return $approval;
        });
    }

    /**
     * Changes an approval, or renews it with a new expiry date. A new expiry
     * date starts the reminders again.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(CourseApproval $approval, array $data, User $actor): CourseApproval
    {
        $before = $approval->only(['number', 'hours', 'status', 'approved_on', 'expires_on']);
        $fields = $this->fields($data, $approval->course, $approval);
        self::ensureComplete($fields + $approval->only(['number', 'status']));

        $approval->fill($fields);
        $renewed = $approval->isDirty('expires_on') && $approval->expires_on !== null && ($before['expires_on'] === null || $approval->expires_on->gt($before['expires_on']));

        if ($approval->isDirty('expires_on')) {
            $approval->forceFill(['reminded_days' => null, 'expired_at' => null]);
        }

        $approval->save();

        $this->audit->record($renewed ? 'course.approval_renewed' : 'course.approval_changed', self::AUDIT_SUBJECT, (string) $approval->id, [
            'course_id' => $approval->course_id, 'state' => $approval->state_code, 'delivery' => $approval->delivery->value,
            'changes' => array_keys($approval->getChanges()), 'expires_on' => $approval->expires_on?->toDateString(),
        ], $actor, $approval->course->organisation_id);

        return $approval;
    }

    /** Withdraws an approval (the course loses its label there), or puts it back (Undo). */
    public function setStatus(CourseApproval $approval, string $status, User $actor): CourseApproval
    {
        if (! in_array($status, [CourseApproval::PENDING, CourseApproval::APPROVED, CourseApproval::WITHDRAWN], true)) {
            throw ValidationException::withMessages(['status' => __('approvals.errors.status')]);
        }

        if ($status === CourseApproval::APPROVED) {
            self::ensureComplete(['status' => $status, 'number' => $approval->number]);
        }

        $from = $approval->status;
        $approval->forceFill(['status' => $status])->save();

        $this->audit->record($status === CourseApproval::WITHDRAWN ? 'course.approval_withdrawn' : 'course.approval_changed', self::AUDIT_SUBJECT, (string) $approval->id, [
            'course_id' => $approval->course_id, 'state' => $approval->state_code, 'from' => $from, 'to' => $status,
        ], $actor, $approval->course->organisation_id);

        return $approval;
    }

    /**
     * The daily check (`reos:approvals:check`): reminders 60 and 30 days
     * before expiry, and the expiry itself (the course loses its label in
     * that state). Returns how many reminders and expiries it handled.
     *
     * @return array{reminded: int, expired: int}
     */
    public function check(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $reminded = 0;
        $expired = 0;

        $due = CourseApproval::query()
            ->with('course.owner')
            ->where('status', CourseApproval::APPROVED)
            ->whereNotNull('expires_on')
            ->whereNull('expired_at')
            ->whereDate('expires_on', '<=', $today->addDays(max(CourseApproval::REMIND_DAYS))->toDateString())
            ->get();

        foreach ($due as $approval) {
            $left = $approval->daysLeft($today) ?? 0;

            if ($left < 0) {
                $approval->forceFill(['expired_at' => now()])->save();
                $this->audit->record('course.approval_expired', self::AUDIT_SUBJECT, (string) $approval->id, [
                    'course_id' => $approval->course_id, 'state' => $approval->state_code, 'expires_on' => $approval->expires_on?->toDateString(),
                ], null, $approval->course->organisation_id);
                Notification::send($this->recipients($approval->course), new ApprovalExpiring($approval, 0));
                $expired++;

                continue;
            }

            // The smallest reminder window it's inside that hasn't been sent yet (60, then 30).
            $window = collect(CourseApproval::REMIND_DAYS)->sort()->first(static fn (int $days): bool => $left <= $days);

            if (is_int($window) && ($approval->reminded_days === null || $approval->reminded_days > $window)) {
                $approval->forceFill(['reminded_days' => $window])->save();
                Notification::send($this->recipients($approval->course), new ApprovalExpiring($approval, $left));
                $reminded++;
            }
        }

        return ['reminded' => $reminded, 'expired' => $expired];
    }

    /** Whether the person may manage a course's approvals: its school's admins, or REOS for its own courses. */
    public function canManage(User $user, Course $course, ?Organisation $school): bool
    {
        if ($course->organisation_id === null) {
            return $user->can('content.manage');
        }

        return $school !== null && $school->id === $course->organisation_id;
    }

    /** @return Collection<int, User> */
    private function recipients(Course $course): Collection
    {
        return $course->owner === null
            ? $this->memberships->holders(Role::SuperAdmin)
            : $this->memberships->holdersIn(Role::SchoolAdmin, $course->owner);
    }

    /**
     * The editable fields present in the input, normalised.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data, Course $course, ?CourseApproval $approval = null): array
    {
        $fields = [];

        foreach (['number', 'note'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = trim(self::string($data[$key]));
                $fields[$key] = $value === '' ? null : $value;
            }
        }

        foreach (['approved_on', 'expires_on'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = self::string($data[$key]);
                $fields[$key] = $value === '' ? null : $value;
            }
        }

        foreach (['hours', 'max_hours_per_day'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = is_numeric($data[$key]) ? (float) $data[$key] : null;
            }
        }

        foreach (['checkin_minutes', 'presence_percent', 'makeup_min_percent', 'makeup_days'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = is_numeric($data[$key]) ? (int) $data[$key] : null;
            }
        }

        if (array_key_exists('status', $data)) {
            $fields['status'] = self::string($data['status']);
        }

        if (array_key_exists('records_live', $data)) {
            $fields['records_live'] = filter_var($data['records_live'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($approval === null) {
            $fields['hours'] ??= $course->hours === null ? null : (float) $course->hours;
            $fields['status'] ??= CourseApproval::APPROVED;
        }

        return $fields;
    }

    /** @param  array<string, mixed>  $fields */
    private static function ensureComplete(array $fields): void
    {
        if (($fields['status'] ?? null) === CourseApproval::APPROVED && trim(self::string($fields['number'] ?? '')) === '') {
            throw ValidationException::withMessages(['number' => __('approvals.errors.number')]);
        }

        $approved = $fields['approved_on'] ?? null;
        $expires = $fields['expires_on'] ?? null;

        if (is_string($approved) && is_string($expires) && $expires < $approved) {
            throw ValidationException::withMessages(['expires_on' => __('approvals.errors.expiry')]);
        }
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
