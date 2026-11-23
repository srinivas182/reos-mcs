<?php

declare(strict_types=1);

namespace App\Content\Instructors;

use App\Authorization\Role;
use App\Content\Avatars\AvatarConsents;
use App\Models\AvatarConsent;
use App\Models\Course;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Services\Tenancy\Invitations;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A school's instructors (A7.1b; school admin web screen 52): who teaches,
 * the courses each teaches (and so may edit, A7.2), the approvals in force
 * on those courses, their avatar consent, and invitations not yet accepted.
 * Revoke ends the instructor role at the school (Undo puts it back).
 */
final readonly class SchoolInstructors
{
    private const AUDIT_SUBJECT = 'instructor';

    public function __construct(
        private Memberships $memberships,
        private AvatarConsents $avatars,
        private Invitations $invitations,
        private AuditLogger $audit,
    ) {}

    /**
     * @return list<array{id: int, name: string, email: string|null, status: string, courses: list<array{id: int, title: string}>, approvals: int, avatar: array{status: string|null, given_at: string|null, lessons_due_by: string|null}, rating: float|null, price: int|null}>
     */
    public function list(Organisation $school): array
    {
        $today = CarbonImmutable::today();
        $courses = Course::query()->where('organisation_id', $school->id)->with(['instructors:id', 'approvals'])->get();
        $avatars = AvatarConsent::query()->where('organisation_id', $school->id)->get()->keyBy('user_id');

        $rows = $this->memberships->holdersIn(Role::Instructor, $school)->sortBy(static fn (User $u): string => mb_strtolower((string) $u->name))->values()->map(function (User $user) use ($courses, $avatars, $today): array {
            $teaches = $courses->filter(static fn (Course $c): bool => $c->instructors->contains('id', $user->id))->values();
            $avatar = $avatars->get($user->id);

            return [
                'id' => $user->id,
                'name' => $user->name ?? (string) $user->email,
                'email' => $user->email,
                'status' => 'active',
                'courses' => array_values($teaches->map(static fn (Course $c): array => ['id' => $c->id, 'title' => $c->title])->all()),
                'approvals' => $teaches->sum(static fn (Course $c): int => $c->approvals->filter(static fn ($a): bool => $a->valid($today))->count()),
                'avatar' => [
                    'status' => $avatar?->status,
                    'given_at' => $avatar?->given_at?->toIso8601String(),
                    'lessons_due_by' => $avatar?->lessons_due_by?->toDateString(),
                ],
                // Ratings and session prices arrive with reviews and the marketplace.
                'rating' => null,
                'price' => null,
            ];
        });

        $invited = $this->invitations->of($school)
            ->filter(static fn (Invitation $i): bool => $i->role === Role::Instructor)
            ->map(static fn (Invitation $i): array => [
                'id' => -$i->id, 'name' => $i->name ?? (string) ($i->email ?? $i->phone), 'email' => $i->email, 'status' => 'invited',
                'courses' => [], 'approvals' => 0, 'avatar' => ['status' => null, 'given_at' => null, 'lessons_due_by' => null], 'rating' => null, 'price' => null,
            ]);

        return [...array_values($rows->all()), ...array_values($invited->all())];
    }

    /**
     * Sets the courses an instructor teaches (the school's own courses only).
     *
     * @param  list<int>  $courseIds
     */
    public function assign(Organisation $school, User $instructor, array $courseIds, User $actor): void
    {
        $this->ensureInstructor($school, $instructor);
        $own = Course::query()->where('organisation_id', $school->id)->whereIn('id', $courseIds)->pluck('id')->all();
        $all = Course::query()->where('organisation_id', $school->id)->pluck('id')->all();

        DB::transaction(function () use ($instructor, $own, $all, $school, $actor): void {
            DB::table('course_instructors')->where('user_id', $instructor->id)->whereIn('course_id', $all)->whereNotIn('course_id', $own)->delete();

            foreach ($own as $id) {
                DB::table('course_instructors')->insertOrIgnore(['course_id' => $id, 'user_id' => $instructor->id, 'created_at' => now(), 'updated_at' => now()]);
            }

            $this->audit->record('instructor.courses_changed', self::AUDIT_SUBJECT, (string) $instructor->id, ['courses' => $own], $actor, $school->id);
        });
    }

    /** Ends the instructor role at the school; the avatar can't be used from now. */
    public function revoke(Organisation $school, User $instructor, User $actor): void
    {
        $this->ensureInstructor($school, $instructor);

        DB::transaction(function () use ($school, $instructor, $actor): void {
            $this->memberships->revoke($instructor, Role::Instructor, $school, $actor);
            $this->avatars->endFor($instructor, $school, $actor);
            $this->audit->record('instructor.revoked', self::AUDIT_SUBJECT, (string) $instructor->id, [], $actor, $school->id);
        });
    }

    /** Undo of Revoke: the role comes back, and the avatar too if its sample is still there. */
    public function restore(Organisation $school, User $instructor, User $actor): void
    {
        if ($this->memberships->find($instructor, Role::Instructor, $school->id) !== null) {
            return;
        }

        DB::transaction(function () use ($school, $instructor, $actor): void {
            $this->memberships->grant($instructor, Role::Instructor, $school, $actor);
            $this->avatars->resume($instructor, $school);
            $this->audit->record('instructor.restored', self::AUDIT_SUBJECT, (string) $instructor->id, [], $actor, $school->id);
        });
    }

    private function ensureInstructor(Organisation $school, User $instructor): void
    {
        if ($this->memberships->find($instructor, Role::Instructor, $school->id) === null) {
            throw ValidationException::withMessages(['instructor' => __('instructors.errors.notInstructor')]);
        }
    }
}
