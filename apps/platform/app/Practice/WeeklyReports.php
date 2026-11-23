<?php

declare(strict_types=1);

namespace App\Practice;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\LessonProgress;
use App\Models\ReadinessSnapshot;
use App\Models\ReportShare;
use App\Models\SpeedRound;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Authorization\Memberships;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The weekly progress report (candidate screen 55, A9.2e, ADR 0035):
 * Monday to Sunday on the candidates' time (Texas). Time studied (lesson
 * seat time, tests and speed rounds), minutes a day against the plan,
 * questions answered, flashcards reviewed, the readiness change, lessons
 * finished and course hours, the weakest topic and the streak. Sent every
 * Sunday evening by push and email; shared with the school or a study
 * buddy only when the candidate chooses.
 *
 * @phpstan-type Report array{week_start: string, week_end: string, first_name: string, minutes: int, goal_minutes: int, per_day: list<int>, questions: int, cards: int, readiness: array{now: int|null, before: int|null, change: int|null}, lessons: int, hours: float, weakest: string|null, streak: int}
 */
final readonly class WeeklyReports
{
    /** A test longer than this (left open) counts as this long. */
    private const TEST_CAP_SECONDS = 4 * 3600;

    public const LINK_DAYS = 14;

    public function __construct(private Readiness $readiness) {}

    /** The Monday of the week holding this day (candidates' time). */
    public static function weekOf(?CarbonImmutable $day = null): CarbonImmutable
    {
        return ($day ?? StudyPlans::today())->setTimezone(StudyPlans::TIMEZONE)->startOfDay()->startOfWeek(CarbonImmutable::MONDAY);
    }

    /** @return Report */
    public function for(User $user, CarbonImmutable $monday): array
    {
        $start = self::weekOf($monday);
        $end = $start->addDays(6)->endOfDay();
        $days = array_map(static fn (int $i): string => $start->addDays($i)->toDateString(), range(0, 6));
        $perDay = array_fill_keys($days, 0);

        foreach (DB::table('seat_time_days')->where('user_id', $user->id)->whereBetween('day', [$days[0], $days[6]])->get(['day', 'seconds']) as $row) {
            $day = substr((string) $row->day, 0, 10);
            if (array_key_exists($day, $perDay) && is_numeric($row->seconds)) {
                $perDay[$day] += (int) $row->seconds;
            }
        }

        $utc = [$start->utc(), $end->utc()];
        $questions = 0;
        foreach (TestAttempt::query()->where('user_id', $user->id)->whereNotNull('submitted_at')->whereBetween('submitted_at', $utc)->get(['started_at', 'submitted_at', 'answers']) as $test) {
            $day = CarbonImmutable::parse($test->submitted_at)->setTimezone(StudyPlans::TIMEZONE)->toDateString();
            $perDay[$day] = ($perDay[$day] ?? 0) + min(self::TEST_CAP_SECONDS, (int) $test->started_at->diffInSeconds($test->submitted_at));
            $questions += count($test->answers ?? []);
        }
        foreach (SpeedRound::query()->where('user_id', $user->id)->whereNotNull('finished_at')->whereBetween('finished_at', $utc)->get(['finished_at', 'answered']) as $round) {
            $day = CarbonImmutable::parse($round->finished_at)->setTimezone(StudyPlans::TIMEZONE)->toDateString();
            $perDay[$day] = ($perDay[$day] ?? 0) + SpeedRounds::SECONDS;
            $questions += $round->answered ?? 0;
        }

        $seat = (int) DB::table('seat_time_days')->where('user_id', $user->id)->whereBetween('day', [$days[0], $days[6]])->sum('seconds');
        $before = ReadinessSnapshot::query()->where('user_id', $user->id)->where('day', '<', $days[0])->latest('day')->value('percent');
        $now = ReadinessSnapshot::query()->where('user_id', $user->id)->where('day', '<=', $days[6])->latest('day')->value('percent');
        $now = is_numeric($now) ? (int) $now : null;
        $before = is_numeric($before) ? (int) $before : null;
        $seconds = array_sum($perDay);

        return [
            'week_start' => $days[0], 'week_end' => $days[6],
            'first_name' => Str::before(trim((string) $user->name), ' '),
            'minutes' => intdiv($seconds, 60),
            'goal_minutes' => ($user->minutes_per_day ?? StudyPlans::DEFAULT_MINUTES) * 7,
            'per_day' => array_values(array_map(static fn (int $s): int => intdiv($s, 60), $perDay)),
            'questions' => $questions,
            'cards' => DB::table('flashcard_reviews')->where('user_id', $user->id)->whereBetween('reviewed_at', $utc)->count(),
            'readiness' => ['now' => $now, 'before' => $before, 'change' => $now !== null && $before !== null ? $now - $before : null],
            'lessons' => LessonProgress::query()->where('user_id', $user->id)->whereBetween('completed_at', $utc)->count(),
            'hours' => round($seat / 3600, 1),
            'weakest' => $this->readiness->of($user)['weakest'][0] ?? null,
            'streak' => $this->readiness->streak($user, $end->startOfDay()),
        ];
    }

    /** The candidate's school, when they study through one: who "Share with school" reaches. */
    public static function school(User $user): ?int
    {
        foreach (app(Memberships::class)->of($user) as $membership) {
            if ($membership->role === Role::Candidate && $membership->organisation->type === OrganisationType::School) {
                return $membership->organisation->id;
            }
        }

        return null;
    }

    /**
     * Share a week's report: with the school, or as a link for a study
     * buddy (first name only; 14 days). Sharing again updates the share.
     *
     * @throws ValidationException with no school to share with
     */
    public function share(User $user, CarbonImmutable $monday, string $with): ReportShare
    {
        $report = $this->for($user, $monday);
        $school = $with === 'school' ? (self::school($user) ?? throw ValidationException::withMessages(['with' => __('practice.reports.errors.noSchool')])) : null;
        $existing = ReportShare::query()->where('user_id', $user->id)->whereDate('week_start', $report['week_start'])->whereNull('revoked_at')
            ->when($school !== null, static fn ($q) => $q->where('organisation_id', $school), static fn ($q) => $q->whereNull('organisation_id'))->first();

        $share = $existing ?? new ReportShare(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'organisation_id' => $school, 'week_start' => $report['week_start']]);
        $share->fill(['report' => $report, 'expires_at' => $school === null ? now()->addDays(self::LINK_DAYS) : null])->save();

        return $share;
    }

    /**
     * Shares for this week, for the screen.
     *
     * @return array{school: bool, can_share_school: bool, link: string|null}
     */
    public function shares(User $user, CarbonImmutable $monday): array
    {
        $week = self::weekOf($monday)->toDateString();
        $shares = ReportShare::query()->where('user_id', $user->id)->whereDate('week_start', $week)->whereNull('revoked_at')->get();
        $link = $shares->first(static fn (ReportShare $s): bool => $s->organisation_id === null && $s->expires_at !== null && $s->expires_at->isFuture());

        return [
            'school' => $shares->contains(static fn (ReportShare $s): bool => $s->organisation_id !== null),
            'can_share_school' => self::school($user) !== null,
            'link' => $link === null ? null : route('reports.shared', $link->uuid),
        ];
    }
}
