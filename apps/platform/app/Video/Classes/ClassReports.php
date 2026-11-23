<?php

declare(strict_types=1);

namespace App\Video\Classes;

use App\Authorization\Role;
use App\Models\ClassAttendance;
use App\Models\ClassCheck;
use App\Models\ClassCheckAnswer;
use App\Models\LiveClass;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VideoRecording;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use App\Notifications\ClassReportReady;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The CE class report (A10.2c, ADR 0040; instructor i13, web w505). When a
 * CE class ends, each registered student's seat time and attendance checks
 * are measured against the approval's rules:
 *
 * - **earned**: at least the approval's share of the class's time, and every check answered;
 * - **review** (borderline): within a tenth of the seat time needed and at most one check missed;
 * - **not earned**: anything else, including not joining.
 *
 * The instructor accepts or rejects each borderline case with a reason,
 * then Confirms and sends it to the school, whose admins are told and can
 * open it (the school reports completions to the state). Every decision is
 * audited.
 *
 * @phpstan-type ReportRow array{user_id: int, name: string, minutes_present: int, checks_answered: int, checks_total: int, outcome: 'earned'|'review'|'not_earned', reasons: list<string>, decision: 'accepted'|'rejected'|null, decision_reason: string|null, earned: bool}
 * @phpstan-type Report array{class: array{id: string, title: string, course: string, starts_at: string, ends_at: string, minutes: int, approval: string|null, ce_hours: float|null}, rules: array{presence_percent: int, required_minutes: int, checkin_minutes: int, checks: int}, status: 'none'|'draft'|'sent', sent_at: string|null, sent_by: string|null, can_decide: bool, can_send: bool, counts: array{joined: int, earned: int, not_earned: int, review: int, undecided: int}, students: list<ReportRow>}
 */
final readonly class ClassReports
{
    public const AUDIT_SUBJECT = 'live_class';

    /** Borderline: this share of the seat time needed, or more. */
    private const REVIEW_SHARE = 0.9;

    public function __construct(private LiveClasses $classes, private Memberships $memberships, private AuditLogger $audit) {}

    /** When a CE class ends: measures everyone, and tells the instructor it's ready. */
    public function prepare(VideoSession $session): void
    {
        $class = $session->liveClass;

        if ($class === null || ! $class->ce() || $class->report_status !== LiveClass::REPORT_NONE) {
            return;
        }

        $required = $this->requiredSeconds($session, $class);
        $checks = ClassCheck::query()->where('video_session_id', $session->id)->pluck('id')->all();
        $answers = ClassCheckAnswer::query()->whereIn('class_check_id', $checks)->get()->countBy('user_id')->all();

        DB::transaction(function () use ($session, $class, $required, $checks, $answers): void {
            foreach ($session->participants()->where('role', VideoSessionParticipant::GUEST)->get() as $participant) {
                $answered = (int) ($answers[$participant->user_id] ?? 0);
                $missed = count($checks) - $answered;
                $seconds = $participant->seconds_present;
                $reasons = [];

                if ($participant->first_joined_at === null) {
                    $reasons[] = 'not_joined';
                } else {
                    if ($seconds < $required) {
                        $reasons[] = 'seat_time';
                    }

                    if ($missed > 0) {
                        $reasons[] = 'checks';
                    }
                }

                $outcome = match (true) {
                    $reasons === [] => ClassAttendance::EARNED,
                    $participant->first_joined_at !== null && $seconds >= $required * self::REVIEW_SHARE && $missed <= 1 => ClassAttendance::REVIEW,
                    default => ClassAttendance::NOT_EARNED,
                };

                ClassAttendance::query()->updateOrCreate(
                    ['video_session_id' => $session->id, 'user_id' => $participant->user_id],
                    ['seconds_present' => $seconds, 'checks_answered' => $answered, 'checks_total' => count($checks), 'outcome' => $outcome, 'reasons' => $reasons],
                );
            }

            $class->forceFill(['report_status' => LiveClass::REPORT_DRAFT])->save();
        });

        $session->host->notify(new ClassReportReady($session));
    }

    /** @return Report */
    public function report(VideoSession $session, User $viewer): array
    {
        $class = $this->classes->classOf($session);
        $host = $this->ensureCanView($session, $viewer);
        $rows = ClassAttendance::query()->where('video_session_id', $session->id)->with('user')->get()
            ->sortBy(static fn (ClassAttendance $a): string => [ClassAttendance::REVIEW => '0', ClassAttendance::NOT_EARNED => '1', ClassAttendance::EARNED => '2'][$a->outcome].($a->user->name ?? ''));

        $students = [];

        foreach ($rows as $row) {
            $students[] = [
                'user_id' => $row->user_id,
                'name' => $row->user->name ?? '',
                'minutes_present' => intdiv($row->seconds_present, 60),
                'checks_answered' => $row->checks_answered,
                'checks_total' => $row->checks_total,
                'outcome' => match ($row->outcome) {
                    ClassAttendance::EARNED => 'earned',
                    ClassAttendance::REVIEW => 'review',
                    default => 'not_earned',
                },
                'reasons' => array_values(array_filter($row->reasons, is_string(...))),
                'decision' => match ($row->decision) {
                    ClassAttendance::ACCEPTED => 'accepted',
                    ClassAttendance::REJECTED => 'rejected',
                    default => null,
                },
                'decision_reason' => $row->decision_reason,
                'earned' => $row->earned(),
            ];
        }

        $draft = $class->report_status === LiveClass::REPORT_DRAFT;
        $undecided = count(array_filter($students, static fn (array $s): bool => $s['outcome'] === 'review' && $s['decision'] === null));
        $hours = $class->approval?->hours;

        return [
            'class' => [
                'id' => $session->uuid,
                'title' => $session->title,
                'course' => $class->course->title,
                'starts_at' => $session->starts_at->toIso8601String(),
                'ends_at' => $session->ends_at->toIso8601String(),
                'minutes' => (int) $session->starts_at->diffInMinutes($session->ends_at),
                'approval' => $class->approval === null ? null : trim($class->approval->state_code.' '.$class->approval->number),
                'ce_hours' => is_numeric($hours) ? (float) $hours : null,
            ],
            'rules' => [
                'presence_percent' => $class->presence_percent ?? 100,
                'required_minutes' => intdiv($this->requiredSeconds($session, $class), 60),
                'checkin_minutes' => $class->checkin_minutes ?? 0,
                'checks' => ClassCheck::query()->where('video_session_id', $session->id)->count(),
            ],
            'status' => match ($class->report_status) {
                LiveClass::REPORT_DRAFT => 'draft',
                LiveClass::REPORT_SENT => 'sent',
                default => 'none',
            },
            'sent_at' => $class->report_sent_at?->toIso8601String(),
            'sent_by' => $class->report_sent_by === null ? null : User::query()->find($class->report_sent_by)?->name,
            'can_decide' => $host && $draft,
            'can_send' => $host && $draft && $undecided === 0,
            'counts' => [
                'joined' => count(array_filter($students, static fn (array $s): bool => ! in_array('not_joined', $s['reasons'], true))),
                'earned' => count(array_filter($students, static fn (array $s): bool => $s['earned'])),
                // Not earned: by the rules, or rejected on review (undecided cases are neither yet).
                'not_earned' => count(array_filter($students, static fn (array $s): bool => ! $s['earned'] && ($s['outcome'] !== 'review' || $s['decision'] !== null))),
                'review' => count(array_filter($students, static fn (array $s): bool => $s['outcome'] === 'review')),
                'undecided' => $undecided,
            ],
            'students' => $students,
        ];
    }

    /** Accept or reject a borderline case, with a reason (audited). */
    public function decide(VideoSession $session, User $host, int $userId, bool $accept, string $reason): void
    {
        $class = $this->ensureDraftHost($session, $host);
        $row = ClassAttendance::query()->where('video_session_id', $session->id)->where('user_id', $userId)->first() ?? throw new NotFoundHttpException;

        if ($row->outcome !== ClassAttendance::REVIEW) {
            throw ValidationException::withMessages(['user' => __('classes.report.errors.notReview')]);
        }

        $before = $row->decision;
        $row->forceFill(['decision' => $accept ? ClassAttendance::ACCEPTED : ClassAttendance::REJECTED, 'decision_reason' => $reason, 'decided_by' => $host->id, 'decided_at' => now()])->save();

        $this->audit->record('class_report.decided', self::AUDIT_SUBJECT, (string) $class->id, [
            'session' => $session->uuid, 'student' => $userId, 'decision' => $row->decision, 'previous' => $before, 'reason' => $reason,
            'minutes_present' => intdiv($row->seconds_present, 60), 'checks' => "{$row->checks_answered}/{$row->checks_total}",
        ], $host, $session->organisation_id);
    }

    /** Confirm and send to school: every borderline case decided first (audited; the school's admins are told). */
    public function send(VideoSession $session, User $host): void
    {
        $class = $this->ensureDraftHost($session, $host);

        if (ClassAttendance::query()->where('video_session_id', $session->id)->where('outcome', ClassAttendance::REVIEW)->whereNull('decision')->exists()) {
            throw ValidationException::withMessages(['report' => __('classes.report.errors.undecided')]);
        }

        $class->forceFill(['report_status' => LiveClass::REPORT_SENT, 'report_sent_by' => $host->id, 'report_sent_at' => now()])->save();
        $rows = ClassAttendance::query()->where('video_session_id', $session->id)->get();

        $this->audit->record('class_report.sent', self::AUDIT_SUBJECT, (string) $class->id, [
            'session' => $session->uuid, 'course_id' => $class->course_id, 'approval_id' => $class->course_approval_id,
            'earned' => array_values($rows->filter(static fn (ClassAttendance $a): bool => $a->earned())->pluck('user_id')->all()),
            'not_earned' => array_values($rows->reject(static fn (ClassAttendance $a): bool => $a->earned())->pluck('user_id')->all()),
        ], $host, $session->organisation_id);

        $school = $session->organisation_id === null ? null : Organisation::query()->find($session->organisation_id);

        if ($school !== null) {
            Notification::send($this->memberships->holdersIn(Role::SchoolAdmin, $school), new ClassReportReady($session, ClassReportReady::SENT, $host->name ?? ''));
        }
    }

    /** Recording a live class (A10.2c): a CE class only when its approval allows it; then it goes to the course. */
    public function mayRecord(VideoSession $session): bool
    {
        $class = $session->liveClass;

        return $class === null || $class->approval === null || $class->approval->records_live;
    }

    /** Whether a past class has a recording ready for its students. */
    public static function hasRecording(VideoSession $session): bool
    {
        return $session->recordings()->where('status', VideoRecording::READY)->exists();
    }

    private function requiredSeconds(VideoSession $session, LiveClass $class): int
    {
        $length = (int) $session->starts_at->diffInSeconds($session->ends_at);

        return (int) ceil($length * ($class->presence_percent ?? 100) / 100);
    }

    /** The host, or an admin of the school the class belongs to. Returns whether it's the host. */
    private function ensureCanView(VideoSession $session, User $viewer): bool
    {
        if ($session->host_id === $viewer->id) {
            return true;
        }

        if ($session->organisation_id !== null && $this->memberships->find($viewer, Role::SchoolAdmin, $session->organisation_id) !== null) {
            return false;
        }

        throw new NotFoundHttpException;
    }

    private function ensureDraftHost(VideoSession $session, User $host): LiveClass
    {
        $class = $this->classes->classOf($session);

        if ($session->host_id !== $host->id) {
            throw new NotFoundHttpException;
        }

        if ($class->report_status !== LiveClass::REPORT_DRAFT) {
            throw ValidationException::withMessages(['report' => __('classes.report.errors.notDraft')]);
        }

        return $class;
    }
}
