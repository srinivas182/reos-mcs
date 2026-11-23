<?php

declare(strict_types=1);

namespace App\Practice;

use App\Content\Learning\Learning;
use App\Models\CalendarEvent;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\StudyPlan;
use App\Models\StudyTask;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Calendar\CalendarEvents;
use App\Services\Calendar\CalendarRefused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The candidate's study plan (A8.3, ADR 0032; candidate screens 9 and 12,
 * web screens 4 and 6).
 *
 * Built from the exam month (the exam on its 15th, never moved), minutes a
 * day, the diagnostic's and later tests' weak topics, and the courses the
 * person can open:
 *
 * - day one is the 45-minute diagnostic until it's done;
 * - each day holds exactly the person's minutes: the next lessons in order
 *   (a long lesson spreads over days), and a 10-minute quiz on a weak topic
 *   most days;
 * - every seventh day, and the last three before the exam, are review days of
 *   weak-topic quizzes;
 * - missed practice is carried into the next three days' practice slots,
 *   still within the minutes; lessons simply continue where they are. When
 *   the lessons no longer fit before the exam, the plan says by how much,
 *   gently, and never moves the date.
 *
 * Rebuilt when a day is missed, the minutes or exam month change, or a test is
 * finished. The next 14 days are placed on the person's calendar as study
 * blocks at their reminder time, updated in place on each rebuild.
 */
final readonly class StudyPlans
{
    /** Candidates study on Texas time (the launch state). */
    public const TIMEZONE = 'America/Chicago';

    public const DEFAULT_MINUTES = 20;

    public const QUIZ_MINUTES = 10;

    /** Days placed on the calendar ahead. */
    public const CALENDAR_DAYS = 14;

    public function __construct(private Learning $learning, private Readiness $readiness, private CalendarEvents $calendar) {}

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    public function active(User $user): ?StudyPlan
    {
        return StudyPlan::query()->where('user_id', $user->id)->where('active', true)->latest('id')->first();
    }

    /** The plan, rebuilt first if a day was missed since it was made. */
    public function current(User $user): StudyPlan
    {
        $plan = $this->active($user);

        if ($plan === null) {
            return $this->rebuild($user, 'setup');
        }

        $this->settle($user, $plan);
        $today = self::today();
        $missed = StudyTask::query()->where('study_plan_id', $plan->id)->where('day', '<', $today->toDateString())->whereNull('done_at')->exists();
        $madeToday = CarbonImmutable::parse($plan->created_at ?? $today)->setTimezone(self::TIMEZONE)->isSameDay($today);

        return $missed && ! $madeToday ? $this->rebuild($user, 'missed') : $plan;
    }

    public function rebuild(User $user, string $reason): StudyPlan
    {
        $today = self::today();
        $exam = Readiness::examOn($user) ?? $today->addDays(90);
        $minutes = max(10, $user->minutes_per_day ?? self::DEFAULT_MINUTES);
        $weak = $this->readiness->of($user)['weakest'];
        $old = $this->active($user);
        $carried = [];
        foreach ($old === null ? [] : StudyTask::query()->where('study_plan_id', $old->id)->where('day', '<', $today->toDateString())
            ->whereNull('done_at')->whereIn('kind', ['practice', 'review'])->whereNotNull('topic')->pluck('topic') as $topic) {
            if (is_string($topic)) {
                $carried[] = $topic;
            }
        }

        return DB::transaction(function () use ($user, $reason, $today, $exam, $minutes, $weak, $old, $carried): StudyPlan {
            $old?->forceFill(['active' => false])->save();
            $plan = StudyPlan::query()->create([
                'user_id' => $user->id, 'exam_on' => $exam, 'minutes_per_day' => $minutes, 'weak_topics' => $weak, 'reason' => $reason,
                'calendar_events' => $old?->calendar_events, 'active' => true,
            ]);

            $this->fill($user, $plan, $today, $exam, $minutes, $weak, $carried);
            $this->place($user, $plan);

            return $plan;
        });
    }

    /** Move a task to another day (web screen 6, drag). */
    public function move(StudyTask $task, CarbonImmutable $day): void
    {
        $plan = StudyPlan::query()->findOrFail($task->study_plan_id);
        $day = $day->min(CarbonImmutable::parse($plan->exam_on)->subDay())->max(self::today());
        $last = StudyTask::query()->where('study_plan_id', $plan->id)->where('day', $day->toDateString())->max('position');
        $task->forceFill(['day' => $day->toDateString(), 'position' => (is_numeric($last) ? (int) $last : -1) + 1])->save();
        $this->place(User::query()->findOrFail($task->user_id), $plan);
    }

    /**
     * What's left of the lessons, in order: the next lesson's remaining
     * minutes first.
     *
     * @return list<array{lesson: int, minutes: int}>
     */
    public function queue(User $user): array
    {
        $courses = array_filter($this->learning->courses($user), static fn (array $c): bool => in_array($c['type'], ['qualifying', 'test_prep'], true));
        usort($courses, static fn (array $a, array $b): int => [$a['type'] === 'qualifying' ? 0 : 1, $a['title']] <=> [$b['type'] === 'qualifying' ? 0 : 1, $b['title']]);
        $progress = LessonProgress::query()->where('user_id', $user->id)->get()->keyBy('lesson_id');
        $queue = [];

        foreach ($courses as $course) {
            foreach ($course['lessons'] as $lesson) {
                if ($lesson['locked'] || $lesson['done']) {
                    continue;
                }
                $counted = (int) ($progress->get($lesson['id'])->counted_seconds ?? 0);
                $queue[] = ['lesson' => $lesson['id'], 'minutes' => max(5, (int) ceil((($lesson['minutes'] ?? 10) * 60 - $counted) / 60))];
            }
        }

        return $queue;
    }

    /** Marks today's and earlier tasks done from what actually happened. */
    public function settle(User $user, StudyPlan $plan): void
    {
        $open = StudyTask::query()->where('study_plan_id', $plan->id)->whereNull('done_at')->where('day', '<=', self::today()->toDateString())->get();

        foreach ($open as $task) {
            $from = CarbonImmutable::parse($task->day->toDateString(), self::TIMEZONE)->startOfDay();
            $to = $from->endOfDay();
            $done = match ($task->kind) {
                'diagnostic' => TestAttempt::query()->where('user_id', $user->id)->where('kind', TestAttempt::DIAGNOSTIC)->whereNotNull('submitted_at')->exists(),
                'practice', 'review' => TestAttempt::query()->where('user_id', $user->id)->where('kind', TestAttempt::TOPIC)->when($task->kind === 'practice', fn ($q) => $q->where('topic', $task->topic))
                    ->whereBetween('submitted_at', [$from, $to])->exists(),
                default => $task->lesson_id !== null && (
                    LessonProgress::query()->where('user_id', $user->id)->where('lesson_id', $task->lesson_id)->whereNotNull('completed_at')->exists()
                    || (int) DB::table('seat_time_events')->where('user_id', $user->id)->where('lesson_id', $task->lesson_id)->whereBetween('occurred_at', [$from, $to])->sum('counted') >= $task->minutes * 60
                ),
            };

            if ($done) {
                $task->forceFill(['done_at' => now()])->save();
            }
        }
    }

    /** Minutes of lessons that won't fit before the exam (0 when on track). */
    public function behind(User $user, StudyPlan $plan): int
    {
        $planned = (int) StudyTask::query()->where('study_plan_id', $plan->id)->where('kind', 'lesson')->sum('minutes');
        $needed = array_sum(array_column($this->queue($user), 'minutes'));

        return max(0, $needed - $planned);
    }

    /**
     * @param  list<string>  $weak
     * @param  list<string>  $carried
     */
    private function fill(User $user, StudyPlan $plan, CarbonImmutable $today, CarbonImmutable $exam, int $minutes, array $weak, array $carried): void
    {
        $queue = $this->queue($user);
        $diagnosed = TestAttempt::query()->where('user_id', $user->id)->where('kind', TestAttempt::DIAGNOSTIC)->whereNotNull('submitted_at')->exists();
        $days = max(1, (int) $today->diffInDays($exam, false));
        $rotation = $weak === [] ? array_keys(DiagnosticBank::WEIGHTS) : $weak;
        $turn = 0;
        $rows = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $today->addDays($i);
            $tasks = [];
            $left = $minutes;

            if ($i === 0 && ! $diagnosed) {
                $tasks[] = ['kind' => 'diagnostic', 'lesson_id' => null, 'topic' => null, 'minutes' => DiagnosticBank::MINUTES, 'why' => 'baseline'];
                $left = 0;
            } elseif (($i + 1) % 7 === 0 || $i >= $days - 3) {
                // Review day: weak-topic quizzes only.
                while ($left >= self::QUIZ_MINUTES) {
                    $tasks[] = ['kind' => 'review', 'lesson_id' => null, 'topic' => $rotation[$turn++ % count($rotation)], 'minutes' => self::QUIZ_MINUTES, 'why' => 'review'];
                    $left -= self::QUIZ_MINUTES;
                }
            } else {
                // Missed practice first (the next three days), then a weak topic most days.
                $catchUp = $i <= 3 ? array_shift($carried) : null;
                $practice = $catchUp ?? (($weak !== [] || $diagnosed) && $minutes >= 2 * self::QUIZ_MINUTES && $i % 3 !== 2 ? $rotation[$turn++ % count($rotation)] : null);

                if ($practice !== null && $left >= self::QUIZ_MINUTES) {
                    $tasks[] = ['kind' => 'practice', 'lesson_id' => null, 'topic' => $practice, 'minutes' => self::QUIZ_MINUTES, 'why' => $catchUp !== null ? 'catch_up' : 'weak'];
                    $left -= self::QUIZ_MINUTES;
                }

                while ($left > 0 && $queue !== []) {
                    $take = min($left, $queue[0]['minutes']);
                    $tasks[] = ['kind' => 'lesson', 'lesson_id' => $queue[0]['lesson'], 'topic' => null, 'minutes' => $take, 'why' => 'next'];
                    $left -= $take;
                    $queue[0]['minutes'] -= $take;
                    if ($queue[0]['minutes'] <= 0) {
                        array_shift($queue);
                    }
                }

                // Lessons all planned: the rest of the day is practice.
                while ($left >= self::QUIZ_MINUTES) {
                    $tasks[] = ['kind' => 'practice', 'lesson_id' => null, 'topic' => $rotation[$turn++ % count($rotation)], 'minutes' => self::QUIZ_MINUTES, 'why' => 'weak'];
                    $left -= self::QUIZ_MINUTES;
                }
            }

            // Exactly the minutes chosen: a few left over go to the day's last quiz.
            $last = array_key_last($tasks);
            if ($left > 0 && $last !== null && in_array($tasks[$last]['kind'], ['practice', 'review'], true)) {
                $tasks[$last]['minutes'] += $left;
            } elseif ($left > 0 && $tasks !== []) {
                $tasks[] = ['kind' => 'practice', 'lesson_id' => null, 'topic' => $rotation[$turn++ % count($rotation)], 'minutes' => $left, 'why' => 'weak'];
            }

            foreach ($tasks as $position => $task) {
                $rows[] = [...$task, 'study_plan_id' => $plan->id, 'user_id' => $user->id, 'day' => $day->toDateString(), 'position' => $position, 'created_at' => now(), 'updated_at' => now()];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StudyTask::query()->insert($chunk);
        }
    }

    /**
     * The next 14 days as study blocks on the person's calendar, at their
     * reminder time, written to the calendar they chose (B4.1). Existing
     * blocks are moved or retitled in place; days without study lose theirs.
     */
    private function place(User $user, StudyPlan $plan): void
    {
        $today = self::today();
        $minutes = StudyTask::query()->where('study_plan_id', $plan->id)->whereBetween('day', [$today->toDateString(), $today->addDays(self::CALENDAR_DAYS - 1)->toDateString()])
            ->groupBy('day')->selectRaw('day, sum(minutes) as minutes')->pluck('minutes', 'day');
        $existing = $plan->calendar_events ?? [];
        $placed = [];
        [$hour, $minute] = array_map('intval', explode(':', substr($user->reminder_time ?? '19:00', 0, 5)) + [1 => 0]);

        foreach ($minutes as $day => $total) {
            $key = CarbonImmutable::parse(is_string($day) ? $day : 'now')->toDateString();
            $total = is_numeric($total) ? (int) $total : 0;
            $start = CarbonImmutable::parse($key, self::TIMEZONE)->setTime($hour, $minute);
            $values = [
                'title' => __('practice.calendar.title', ['minutes' => $total]), 'kind' => 'study', 'starts_at' => $start->utc(), 'ends_at' => $start->addMinutes($total)->utc(),
                'description' => __('practice.calendar.description'), 'related_type' => 'study_plan', 'related_id' => (string) $plan->id,
            ];
            $event = isset($existing[$key]) ? CalendarEvent::query()->find($existing[$key]) : null;

            try {
                $event = $event === null ? $this->calendar->create($user, $values) : $this->calendar->update($event, $values);
                $placed[$key] = $event->id;
            } catch (CalendarRefused) {
                // The person turned it into their own event in their tool: leave it.
            }
        }

        foreach (array_diff_key($existing, $placed) as $key => $id) {
            $event = CalendarEvent::query()->find($id);
            if ($event !== null && CarbonImmutable::parse((string) $key)->greaterThanOrEqualTo($today->startOfDay()->setTimezone('UTC')->startOfDay())) {
                rescue(fn () => $this->calendar->delete($event), report: false);
            }
        }

        $plan->forceFill(['calendar_events' => $placed])->save();
    }
}
