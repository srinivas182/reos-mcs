<?php

declare(strict_types=1);

namespace App\Video\Classes;

use App\Content\Approvals\CourseApprovals;
use App\Content\CourseStatus;
use App\Content\Learning\Learning;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\LiveClass;
use App\Models\User;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use App\Notifications\SessionBooked;
use App\Notifications\SessionStarting;
use App\Video\VideoSessions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Live classes (A10.2b, ADR 0040; candidate L12, instructor i11 and i12).
 * An instructor schedules a class for a course they teach or their school
 * owns; students of that course register from Live classes (up to the
 * capacity) and join from Today. A course with a live delivery approval in
 * force makes it a CE class: its attendance rules (check interval and
 * share of seat time) are copied onto the class when it's scheduled.
 *
 * @phpstan-type ClassRow array{id: string, title: string, agenda: string|null, course: array{id: int, title: string}, host: string, starts_at: string, ends_at: string, minutes: int, status: 'scheduled'|'live'|'ended'|'cancelled', ce: bool, ce_hours: float|null, checkin_minutes: int|null, registered: int, capacity: int, me: 'host'|'registered'|null, can_register: bool, full: bool, can_join: bool, report: 'none'|'draft'|'sent', has_recording: bool}
 */
final readonly class LiveClasses
{
    /** Lengths a class can run, in minutes. */
    public const LENGTHS = [30, 45, 60, 90, 120, 180, 240];

    /** "Starts in an hour" goes out this many minutes before (with the 10-minute one from B6.3). */
    public const REMIND_HOUR_MINUTES = 60;

    public function __construct(private VideoSessions $sessions, private Learning $learning) {}

    /**
     * Courses the person can teach a class for: live courses they're an
     * instructor on, or that their school owns.
     *
     * @return list<array{id: int, title: string, ce: bool}>
     */
    public function courses(User $host): array
    {
        $school = $this->sessions->hostOrganisation($host);

        if ($school === null) {
            return [];
        }

        return array_values(Course::query()->where('status', CourseStatus::Live)
            ->where(fn ($q) => $q->where('organisation_id', $school->id)->orWhereHas('instructors', fn ($i) => $i->where('users.id', $host->id)))
            ->with('approvals')->orderBy('title')->get()
            ->map(static fn (Course $course): array => ['id' => $course->id, 'title' => $course->title, 'ce' => self::liveApproval($course) !== null])
            ->all());
    }

    /**
     * @param  array{course_id: int, title: string, agenda: string|null, starts_at: CarbonImmutable, minutes: int, capacity: int}  $plan
     */
    public function schedule(User $host, array $plan): VideoSession
    {
        $school = $this->sessions->hostOrganisation($host) ?? throw ValidationException::withMessages(['course_id' => __('sessions.errors.notHost')]);
        $course = in_array($plan['course_id'], array_column($this->courses($host), 'id'), true) ? Course::query()->with('approvals')->find($plan['course_id']) : null;

        if ($course === null) {
            throw ValidationException::withMessages(['course_id' => __('classes.errors.course')]);
        }

        $starts = $plan['starts_at']->utc();
        $ends = $starts->addMinutes($plan['minutes']);

        if ($starts->lessThan(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['starts_at' => __('sessions.errors.past')]);
        }

        if ($this->sessions->busy($host, $starts, $ends)) {
            throw ValidationException::withMessages(['starts_at' => __('sessions.errors.overlap')]);
        }

        $approval = self::liveApproval($course);

        return DB::transaction(function () use ($host, $school, $plan, $course, $approval, $starts, $ends): VideoSession {
            $session = VideoSession::query()->create([
                'uuid' => (string) Str::uuid(), 'organisation_id' => $school->id, 'host_id' => $host->id, 'kind' => VideoSession::LIVE_CLASS,
                'title' => $plan['title'], 'agenda' => $plan['agenda'], 'starts_at' => $starts, 'ends_at' => $ends,
            ]);
            $session->participants()->create(['user_id' => $host->id, 'role' => VideoSessionParticipant::HOST]);
            LiveClass::query()->create([
                'video_session_id' => $session->id,
                'course_id' => $course->id,
                'course_approval_id' => $approval?->id,
                'capacity' => min(LiveClass::MAX_CAPACITY, max(1, $plan['capacity'])),
                'checkin_minutes' => $approval === null ? null : max(1, $approval->checkin_minutes ?? CourseApprovals::CHECKIN_MINUTES),
                'presence_percent' => $approval === null ? null : min(100, max(1, $approval->presence_percent ?? 100)),
            ]);

            return $session;
        });
    }

    /** A student registers: their course, not over, and room left. */
    public function register(User $student, VideoSession $session): void
    {
        $class = $this->classOf($session);

        if (! $this->canSee($student, $class)) {
            throw new NotFoundHttpException;
        }

        if ($session->over() || $session->status === VideoSession::CANCELLED) {
            throw ValidationException::withMessages(['class' => __('sessions.errors.over')]);
        }

        $added = DB::transaction(function () use ($session, $class, $student): bool {
            // One at a time, so the last seat can't go twice.
            LiveClass::query()->whereKey($class->id)->lockForUpdate()->first();

            if ($session->participants()->where('user_id', $student->id)->exists()) {
                return false;
            }

            if ($this->registered($session) >= $class->capacity) {
                throw ValidationException::withMessages(['class' => __('classes.errors.full')]);
            }

            $session->participants()->create(['user_id' => $student->id, 'role' => VideoSessionParticipant::GUEST]);

            return true;
        });

        if ($added) {
            $student->notify(new SessionBooked($session, SessionBooked::BOOKED));
        }
    }

    /** A student cancels their registration before the class starts. */
    public function unregister(User $student, VideoSession $session): void
    {
        $this->classOf($session);
        $participant = $this->sessions->ensureParticipant($session, $student);

        if ($participant->role === VideoSessionParticipant::HOST) {
            throw ValidationException::withMessages(['class' => __('sessions.errors.hostOnly')]);
        }

        if ($session->status !== VideoSession::SCHEDULED || now()->greaterThanOrEqualTo($session->starts_at)) {
            throw ValidationException::withMessages(['class' => __('classes.errors.started')]);
        }

        $participant->delete();
    }

    /**
     * The host's classes: coming up, then the last 30 days.
     *
     * @return list<ClassRow>
     */
    public function forHost(User $host): array
    {
        $sessions = VideoSession::query()->where('host_id', $host->id)->where('kind', VideoSession::LIVE_CLASS)
            ->where('ends_at', '>=', now()->subDays(30))->with(['host', 'liveClass.course', 'liveClass.approval'])->orderBy('starts_at')->get();
        $rows = [];

        foreach ($sessions as $session) {
            $rows[] = $this->row($session, $host);
        }

        return $rows;
    }

    /**
     * A student's classes: coming up for their courses (registered or not),
     * then the ones they were registered for in the last 30 days.
     *
     * @return list<ClassRow>
     */
    public function forStudent(User $student, ?int $course = null): array
    {
        $courses = array_column($this->learning->courses($student), 'id');
        $courses = $course === null ? $courses : array_values(array_intersect($courses, [$course]));
        $upcoming = static function (Builder $q): void {
            $q->whereIn('status', [VideoSession::SCHEDULED, VideoSession::LIVE])->where('ends_at', '>', now());
        };
        $attended = static function (Builder $q) use ($student): void {
            $q->where('ends_at', '>=', now()->subDays(30))->whereHas('participants', fn ($p) => $p->where('user_id', $student->id));
        };
        $sessions = VideoSession::query()->where('kind', VideoSession::LIVE_CLASS)
            ->whereHas('liveClass', fn ($q) => $q->whereIn('course_id', $courses))
            ->where(static fn (Builder $q) => $q->where($upcoming)->orWhere($attended))
            ->with(['host', 'liveClass.course', 'liveClass.approval'])->orderBy('starts_at')->get();

        $rows = [];

        foreach ($sessions as $session) {
            $rows[] = $this->row($session, $student);
        }

        return $rows;
    }

    /** @return ClassRow */
    public function row(VideoSession $session, User $viewer): array
    {
        $class = $this->classOf($session);
        $role = $session->participants()->where('user_id', $viewer->id)->value('role');
        $registered = $this->registered($session);
        $full = $registered >= $class->capacity;
        $open = in_array($session->status, [VideoSession::SCHEDULED, VideoSession::LIVE], true) && ! $session->over();
        $hours = $class->approval?->hours;

        return [
            'id' => $session->uuid,
            'title' => $session->title,
            'agenda' => $session->agenda,
            'course' => ['id' => $class->course_id, 'title' => $class->course->title],
            'host' => $session->host->name ?? '',
            'starts_at' => $session->starts_at->toIso8601String(),
            'ends_at' => $session->ends_at->toIso8601String(),
            'minutes' => (int) $session->starts_at->diffInMinutes($session->ends_at),
            'status' => match ($session->status) {
                VideoSession::LIVE => 'live',
                VideoSession::ENDED => 'ended',
                VideoSession::CANCELLED => 'cancelled',
                default => 'scheduled',
            },
            'ce' => $class->ce(),
            'ce_hours' => $class->ce() && is_numeric($hours) ? (float) $hours : null,
            'checkin_minutes' => $class->checkin_minutes,
            'registered' => $registered,
            'capacity' => $class->capacity,
            'me' => $role === VideoSessionParticipant::HOST ? 'host' : ($role === VideoSessionParticipant::GUEST ? 'registered' : null),
            'can_register' => $role === null && $open && ! $full,
            'full' => $full,
            'can_join' => $role !== null && $session->open(),
            // The CE class report, for the host (A10.2c).
            'report' => $role !== VideoSessionParticipant::HOST ? 'none' : match ($class->report_status) {
                LiveClass::REPORT_DRAFT => 'draft',
                LiveClass::REPORT_SENT => 'sent',
                default => 'none',
            },
            // The recording, for the course's students once the class is over.
            'has_recording' => $role !== null && $session->over() && ClassReports::hasRecording($session),
        ];
    }

    /** "Starts in an hour" to everyone registered, once (`reos:sessions:tick`). */
    public function remindDue(): int
    {
        $due = LiveClass::query()->whereNull('reminded_hour_at')
            ->whereHas('session', fn ($q) => $q->where('status', VideoSession::SCHEDULED)
                ->where('starts_at', '<=', now()->addMinutes(self::REMIND_HOUR_MINUTES))->where('starts_at', '>', now()->addMinutes(VideoSessions::REMIND_MINUTES)))
            ->with('session')->get();

        foreach ($due as $class) {
            $class->forceFill(['reminded_hour_at' => now()])->save();
            $ids = $class->session->participants()->where('role', VideoSessionParticipant::GUEST)->pluck('user_id');
            Notification::send(User::query()->whereIn('id', $ids)->get(), new SessionStarting($class->session));
        }

        return $due->count();
    }

    public function classOf(VideoSession $session): LiveClass
    {
        return $session->liveClass ?? throw new NotFoundHttpException;
    }

    /** Students of the course see its classes. */
    public function canSee(User $user, LiveClass $class): bool
    {
        return in_array($class->course_id, array_column($this->learning->courses($user), 'id'), true);
    }

    private function registered(VideoSession $session): int
    {
        return $session->participants()->where('role', VideoSessionParticipant::GUEST)->count();
    }

    /** The course's live delivery approval in force (A7.1): what makes a class CE. */
    private static function liveApproval(Course $course): ?CourseApproval
    {
        if (! $course->type->needsApproval()) {
            return null;
        }

        return $course->approvals->first(static fn (CourseApproval $a): bool => $a->delivery->live() && $a->valid());
    }
}
