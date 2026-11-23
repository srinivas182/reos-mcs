<?php

declare(strict_types=1);

namespace App\Practice;

use App\Content\Learning\Learning;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\StudyPlan;
use App\Models\StudyTask;
use App\Models\TestAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Today for candidates (A8.3; candidate screen 12, web screen 4): readiness
 * and days to the exam, the streak, Continue (the lesson in progress at its
 * second, or the plan's next), and today's tasks, never more than the
 * minutes the person chose (the diagnostic day aside), each with why.
 *
 * @phpstan-type Task array{id: int, kind: 'diagnostic'|'lesson'|'practice'|'review', why: 'next'|'weak'|'review'|'catch_up'|'baseline', minutes: int, done: bool, day: string, lesson: array{id: int, title: string, course: string}|null, topic: string|null}
 * @phpstan-type Today array{readiness: int|null, portions: array{national: int|null, state: int|null}, ready_to_book: bool, topics: array<string, int|null>, weakest: list<string>, exam_on: string, days_to_exam: int, streak: int, minutes_per_day: int, reminder_time: string|null, diagnostic_done: bool, continue: array{lesson_id: int, title: string, course: string, position: int}|null, tasks: list<Task>, behind_minutes: int, plan: array{id: int, reason: string, built_at: string|null}}
 */
final readonly class CandidateToday
{
    public function __construct(private StudyPlans $plans, private Readiness $readiness, private Learning $learning) {}

    /** @return Today */
    public function for(User $user): array
    {
        $plan = $this->plans->current($user);
        $today = StudyPlans::today();
        $readiness = $this->readiness->of($user);
        $tasks = StudyTask::query()->where('study_plan_id', $plan->id)->where('day', $today->toDateString())->with('lesson.course')->orderBy('position')->get();

        return [
            'readiness' => $readiness['percent'],
            'portions' => $readiness['portions'],
            'ready_to_book' => $readiness['ready_to_book'],
            'topics' => $readiness['topics'],
            'weakest' => $readiness['weakest'],
            'exam_on' => $plan->exam_on->toDateString(),
            'days_to_exam' => max(0, (int) $today->diffInDays(CarbonImmutable::parse($plan->exam_on->toDateString(), StudyPlans::TIMEZONE), false)),
            'streak' => $this->readiness->streak($user, $today),
            'minutes_per_day' => $plan->minutes_per_day,
            'reminder_time' => $user->reminder_time === null ? null : substr($user->reminder_time, 0, 5),
            'diagnostic_done' => TestAttempt::query()->where('user_id', $user->id)->where('kind', TestAttempt::DIAGNOSTIC)->whereNotNull('submitted_at')->exists(),
            'continue' => $this->continue($user, $plan),
            'tasks' => self::tasks($tasks->all()),
            'behind_minutes' => $this->plans->behind($user, $plan),
            'plan' => ['id' => $plan->id, 'reason' => $plan->reason, 'built_at' => $plan->created_at?->toIso8601String()],
        ];
    }

    /**
     * The plan's days from a week's start (web screen 6).
     *
     * @return list<array{day: string, minutes: int, tasks: list<Task>}>
     */
    public function week(User $user, CarbonImmutable $from): array
    {
        $plan = $this->plans->current($user);
        $tasks = StudyTask::query()->where('study_plan_id', $plan->id)->whereBetween('day', [$from->toDateString(), $from->addDays(6)->toDateString()])
            ->with('lesson.course')->orderBy('day')->orderBy('position')->get()->groupBy(static fn (StudyTask $t): string => $t->day->toDateString());
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $from->addDays($i)->toDateString();
            $list = self::tasks(($tasks->get($day) ?? collect())->all());
            $days[] = ['day' => $day, 'minutes' => array_sum(array_column($list, 'minutes')), 'tasks' => $list];
        }

        return $days;
    }

    /**
     * @param  array<int, StudyTask>  $tasks
     * @return list<Task>
     */
    private static function tasks(array $tasks): array
    {
        $list = [];

        foreach ($tasks as $task) {
            $list[] = self::task($task);
        }

        return $list;
    }

    /** @return Task */
    public static function task(StudyTask $task): array
    {
        $kind = match ($task->kind) {
            'diagnostic' => 'diagnostic', 'practice' => 'practice', 'review' => 'review', default => 'lesson',
        };
        $why = match ($task->why) {
            'weak' => 'weak', 'review' => 'review', 'catch_up' => 'catch_up', 'baseline' => 'baseline', default => 'next',
        };

        return [
            'id' => $task->id, 'kind' => $kind, 'why' => $why, 'minutes' => $task->minutes, 'done' => $task->done_at !== null, 'day' => $task->day->toDateString(),
            'lesson' => $task->lesson === null ? null : ['id' => $task->lesson->id, 'title' => $task->lesson->title, 'course' => $task->lesson->course->title],
            'topic' => $task->topic,
        ];
    }

    /** @return array{lesson_id: int, title: string, course: string, position: int}|null */
    private function continue(User $user, StudyPlan $plan): ?array
    {
        $progress = LessonProgress::query()->where('user_id', $user->id)->whereNull('completed_at')->latest('updated_at')->with('lesson.course')->get()
            ->first(fn (LessonProgress $p): bool => $this->learning->find($user, $p->lesson_id) !== null && $this->learning->canOpen($user, $p->lesson));

        if ($progress !== null) {
            return ['lesson_id' => $progress->lesson_id, 'title' => $progress->lesson->title, 'course' => $progress->lesson->course->title, 'position' => $progress->position];
        }

        $next = StudyTask::query()->where('study_plan_id', $plan->id)->where('kind', 'lesson')->whereNull('done_at')->where('day', '>=', StudyPlans::today()->toDateString())
            ->orderBy('day')->orderBy('position')->with('lesson.course')->first();
        $lesson = $next?->lesson;

        return $lesson instanceof Lesson ? ['lesson_id' => $lesson->id, 'title' => $lesson->title, 'course' => $lesson->course->title, 'position' => 0] : null;
    }
}
