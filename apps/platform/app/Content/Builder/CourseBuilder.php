<?php

declare(strict_types=1);

namespace App\Content\Builder;

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Content\Avatars\AvatarConsents;
use App\Content\Delivery;
use App\Content\MediaStatus;
use App\Jobs\IngestLesson;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\SeatTimeChangeAsked;
use App\Services\Ai\AiSettings;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The course builder (A7.2, ADR 0026; instructor web screen 43, school admin
 * course catalogue). School admins build any of their school's courses;
 * instructors build only the courses they teach (screen 52). A school's
 * lessons start as drafts and reach learners only once approved; seat-time
 * changes on an approved course go to the school (for TREC), never straight
 * onto the course.
 */
final readonly class CourseBuilder
{
    /** Spoken words per minute, for "what AI will make". */
    public const WORDS_PER_MINUTE = 140;

    public const QUESTIONS_PER_LESSON = 5;

    public const FLASHCARDS_PER_LESSON = 8;

    public function __construct(
        private Courses $courses,
        private Lessons $lessons,
        private Memberships $memberships,
        private AvatarConsents $avatars,
        private AiSettings $ai,
        private AuditLogger $audit,
    ) {}

    /** The school the person builds for: a school admin or instructor at a school, in that role now. */
    public function school(?Membership $membership): ?Organisation
    {
        return $membership !== null && in_array($membership->role, [Role::SchoolAdmin, Role::Instructor], true) && $membership->organisation->type->value === 'school'
            ? $membership->organisation : null;
    }

    /** @return Collection<int, Course> */
    public function editable(User $user, Membership $membership): Collection
    {
        return Course::query()
            ->where('organisation_id', $membership->organisation->id)
            ->when($membership->role === Role::Instructor, static fn ($q) => $q->whereHas('instructors', static fn ($i) => $i->where('users.id', $user->id)))
            ->orderBy('title')
            ->get();
    }

    public function canEdit(User $user, Membership $membership, Course $course): bool
    {
        if ($course->organisation_id !== $membership->organisation->id) {
            return false;
        }

        return $membership->role === Role::SchoolAdmin
            || ($membership->role === Role::Instructor && $course->instructors()->where('users.id', $user->id)->exists());
    }

    /**
     * A new course for the school; an instructor who creates it teaches it.
     *
     * @param  array{title: string, type: string, delivery?: string|null, hours?: float|int|string|null, description?: string|null}  $data
     */
    public function create(User $actor, Membership $membership, array $data): Course
    {
        return DB::transaction(function () use ($actor, $membership, $data): Course {
            $course = $this->courses->create([
                'title' => $data['title'], 'type' => $data['type'], 'description' => $data['description'] ?? null,
                'states' => ['TX'], 'hours' => $data['hours'] ?? null,
            ], $actor);
            $course->forceFill([
                'organisation_id' => $membership->organisation->id,
                'delivery' => Delivery::tryFrom((string) ($data['delivery'] ?? '')) ?? Delivery::SelfPaced,
                'builder' => self::defaults(),
            ])->save();

            if ($membership->role === Role::Instructor) {
                $course->instructors()->attach($actor->id);
            }

            return $course;
        });
    }

    /** @return array{language: string, lesson_minutes: int, presenter: string} */
    public static function defaults(): array
    {
        return ['language' => 'both', 'lesson_minutes' => 10, 'presenter' => 'stock'];
    }

    /** @return array{language: string, lesson_minutes: int, presenter: string} */
    public function settings(Course $course): array
    {
        $saved = $course->builder ?? [];
        $defaults = self::defaults();

        return [
            'language' => is_string($saved['language'] ?? null) ? $saved['language'] : $defaults['language'],
            'lesson_minutes' => is_int($saved['lesson_minutes'] ?? null) ? $saved['lesson_minutes'] : $defaults['lesson_minutes'],
            'presenter' => is_string($saved['presenter'] ?? null) ? $saved['presenter'] : $defaults['presenter'],
        ];
    }

    /**
     * Saves the builder settings: language (decision 4: both voices), lesson
     * length and presenter (an instructor whose avatar consent is in force, or
     * a stock presenter).
     *
     * @param  array{language: string, lesson_minutes: int, presenter: string}  $settings
     */
    public function saveSettings(Course $course, array $settings, User $actor): void
    {
        if ($settings['presenter'] !== 'stock' && ! in_array($settings['presenter'], array_column($this->presenters($course), 'value'), true)) {
            throw ValidationException::withMessages(['presenter' => __('builder.errors.presenter')]);
        }

        $course->forceFill(['builder' => $settings])->save();
        $this->audit->record('course.builder_changed', Courses::AUDIT_SUBJECT, (string) $course->id, $settings, $actor, $course->organisation_id);
    }

    /**
     * Who can present AI lessons: a stock presenter, or an instructor of the
     * course whose avatar consent is in force for this school.
     *
     * @return list<array{value: string, label: string}>
     */
    public function presenters(Course $course): array
    {
        $list = [];
        $school = $course->owner;

        if ($school !== null) {
            foreach ($course->instructors()->orderBy('name')->get() as $instructor) {
                if ($this->avatars->usable($instructor, $school)) {
                    $list[] = ['value' => 'instructor:'.$instructor->id, 'label' => (string) $instructor->name];
                }
            }
        }

        return $list;
    }

    /**
     * "What AI will make" for a module (A7.3 makes it): the minutes of
     * teaching in its sources, the lessons, check-ins, questions and
     * flashcards, and the credits, rendered in English and Spanish.
     *
     * @return array{words: int, minutes: int, lessons: int, checkins: int, questions: int, flashcards: int, credits: int, sources_ready: int, sources_waiting: int}
     */
    public function plan(Course $course, CourseModule $module, int $checkinMinutes): array
    {
        $sources = $module->sources;
        $ready = $sources->filter(static fn ($s): bool => $s->status === MediaStatus::Ready);
        $words = $ready->sum(static fn ($s): int => $s->words ?? 0);
        $minutes = (int) ceil($words / self::WORDS_PER_MINUTE);
        $length = max(1, $this->settings($course)['lesson_minutes']);
        $lessons = $minutes === 0 ? 0 : (int) ceil($minutes / $length);
        $voices = $this->settings($course)['language'] === 'both' ? 2 : 1;

        return [
            'words' => $words,
            'minutes' => $minutes,
            'lessons' => $lessons,
            'checkins' => $lessons * max(1, (int) ceil($length / max(1, $checkinMinutes))),
            'questions' => $lessons * self::QUESTIONS_PER_LESSON,
            'flashcards' => $lessons * self::FLASHCARDS_PER_LESSON,
            'credits' => $minutes * $voices * $this->ai->cost('ai_lesson_minute'),
            'sources_ready' => $ready->count(),
            'sources_waiting' => $sources->filter(static fn ($s): bool => $s->status === MediaStatus::Processing || $s->status === MediaStatus::AwaitingUpload)->count(),
        ];
    }

    /** A lesson written by hand in a module: a draft until approved. */
    public function addLesson(Course $course, CourseModule $module, string $title, User $actor): Lesson
    {
        $lesson = $this->lessons->create($course, ['title' => $title, 'course_module_id' => $module->id, 'language' => 'en'], $actor);
        $lesson->forceFill(['review_status' => LessonReview::Draft])->save();

        return $lesson;
    }

    /** Moves a lesson up or down within its module. */
    public function move(Lesson $lesson, int $direction, User $actor): void
    {
        $siblings = Lesson::query()->where('course_id', $lesson->course_id)->where('course_module_id', $lesson->course_module_id)->orderBy('position')->orderBy('id')->get()->values();
        $at = $siblings->search(static fn (Lesson $l): bool => $l->id === $lesson->id);
        $to = is_int($at) ? $at + ($direction < 0 ? -1 : 1) : null;

        if ($to === null || $to < 0 || $to >= $siblings->count()) {
            return;
        }

        $other = $siblings->get($to);

        if ($other === null) {
            return;
        }

        DB::transaction(static function () use ($lesson, $other): void {
            [$a, $b] = [$lesson->position, $other->position];
            $lesson->forceFill(['position' => $b === $a ? $a + 1 : $b])->save();
            $other->forceFill(['position' => $a])->save();
        });
        $this->audit->record('lesson.moved', Lessons::AUDIT_SUBJECT, (string) $lesson->id, ['direction' => $direction < 0 ? 'up' : 'down'], $actor, $lesson->course->organisation_id);
    }

    /**
     * Approves a lesson written by hand (its author's own words). AI lessons
     * are approved line by line in the review (A8.1), not here.
     */
    public function approve(Lesson $lesson, User $actor): void
    {
        if ($lesson->review_status !== LessonReview::Draft) {
            throw ValidationException::withMessages(['lesson' => __('builder.errors.notDraft')]);
        }

        if (trim((string) $lesson->body) === '' && ! $lesson->media()->exists()) {
            throw ValidationException::withMessages(['lesson' => __('builder.errors.empty')]);
        }

        $lesson->forceFill(['review_status' => LessonReview::Approved])->save();
        $this->audit->record('lesson.approved', Lessons::AUDIT_SUBJECT, (string) $lesson->id, [], $actor, $lesson->course->organisation_id);
        IngestLesson::dispatch($lesson->id);
    }

    /**
     * An instructor asks the school to change a course's hours: with an
     * approval in force, seat time is the regulator's, so the school decides
     * (screen 43: "seat-time changes go to the school for TREC").
     */
    public function askSeatTimeChange(Course $course, float $hours, string $reason, User $actor): void
    {
        $school = $course->owner ?? throw ValidationException::withMessages(['hours' => __('builder.errors.noSchool')]);

        Notification::send($this->memberships->holdersIn(Role::SchoolAdmin, $school), new SeatTimeChangeAsked($course, $actor, $hours, $reason));
        $this->audit->record('course.seat_time_asked', Courses::AUDIT_SUBJECT, (string) $course->id, ['from' => $course->hours, 'to' => $hours, 'reason' => $reason], $actor, $school->id);
    }

    /** Whether the course's hours are set by an approval (so instructors ask the school instead). */
    public function hoursLocked(Course $course): bool
    {
        return $course->type->needsApproval() && $course->approvals->contains(static fn ($a): bool => $a->valid());
    }
}
