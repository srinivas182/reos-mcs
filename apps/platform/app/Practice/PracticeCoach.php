<?php

declare(strict_types=1);

namespace App\Practice;

use App\Content\CourseStatus;
use App\Content\ExamSuccess;
use App\Models\Course;
use App\Models\Flashcard;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\TestAttempt;
use App\Models\TopicPlan;
use App\Models\User;

/**
 * The practice hub's ranking (candidate screen 38, A9.2b, ADR 0035): what
 * helps most right now, first.
 *
 * - No diagnostic yet: the diagnostic.
 * - A fix plan under way: carry on with it.
 * - The smart drill on the three weakest topics (15 questions, misses
 *   included), refreshed after every session.
 * - Fix the weakest topic, when it's below the pass line and has no plan.
 * - Flashcards due (A9.2d); math lab here when math is among the weakest.
 * - The speed round (A9.2d), then math lab otherwise.
 * - A full simulation: first when readiness is 80% or more (rehearse before
 *   booking), otherwise after practice once readiness reaches 60% or the
 *   exam is 45 days away.
 * - The readiness map.
 *
 * Hands-free and challenges join
 * the ranking as they arrive (A9.2c to A9.3).
 *
 * @phpstan-type Suggestion array{mode: 'diagnostic'|'plan'|'drill'|'fix'|'flashcards'|'speed'|'math'|'simulation'|'readiness'|'exam_success', why: string, topic: string|null, plan_id: int|null, count: int|null, lesson_id: int|null}
 */
final readonly class PracticeCoach
{
    /** Readiness at which the Exam success course is suggested (A9.2f). */
    public const EXAM_SUCCESS_FROM = 60;

    public function __construct(private Readiness $readiness, private Drills $drills) {}

    /**
     * @return array{drill: array{topics: list<string>, questions: int, misses: int, open: int|null}, plans: list<array{id: int, topic: string, done: int, steps: int}>, ranked: list<Suggestion>}
     */
    public function hub(User $user): array
    {
        $readiness = $this->readiness->of($user);
        $topics = $this->drills->weakestTopics($user);
        $plans = TopicPlan::query()->where('user_id', $user->id)->whereNull('completed_at')->latest('id')->get();
        $diagnostic = TestAttempt::query()->where('user_id', $user->id)->where('kind', TestAttempt::DIAGNOSTIC)->whereNotNull('submitted_at')->exists();
        $days = $this->readiness->daysToExam($user);
        $percent = $readiness['percent'] ?? 0;
        $weakest = $readiness['weakest'][0] ?? null;
        $ranked = [];

        if (! $diagnostic) {
            $ranked[] = ['mode' => 'diagnostic', 'why' => 'baseline', 'topic' => null, 'plan_id' => null, 'count' => null];
        }
        $simulation = ['mode' => 'simulation', 'why' => $percent >= 80 ? 'rehearse' : ($days !== null && $days <= 45 ? 'exam_soon' : 'check'), 'topic' => null, 'plan_id' => null, 'count' => null];
        if ($diagnostic && $percent >= 80) {
            $ranked[] = $simulation;
        }
        foreach ($plans as $plan) {
            $ranked[] = ['mode' => 'plan', 'why' => 'carry_on', 'topic' => $plan->topic, 'plan_id' => $plan->id, 'count' => null];
        }
        $ranked[] = ['mode' => 'drill', 'why' => 'weakest', 'topic' => null, 'plan_id' => null, 'count' => null];
        if ($weakest !== null && ! $plans->contains('topic', $weakest)) {
            $ranked[] = ['mode' => 'fix', 'why' => 'weakest', 'topic' => $weakest, 'plan_id' => null, 'count' => null];
        }
        // Exam success (A9.2f): suggested once readiness passes 60%, at the next lesson not finished.
        $examSuccess = $diagnostic && $percent >= self::EXAM_SUCCESS_FROM ? $this->examSuccess($user) : null;
        if ($examSuccess !== null) {
            $ranked[] = ['mode' => 'exam_success', 'why' => 'ready_soon', 'topic' => null, 'plan_id' => null, 'count' => $examSuccess['left'], 'lesson_id' => $examSuccess['lesson_id']];
        }
        $due = Flashcard::query()->where('user_id', $user->id)->where('reps', '>', 0)->where('due_at', '<=', now())->count();
        if ($due > 0) {
            $ranked[] = ['mode' => 'flashcards', 'why' => 'due', 'topic' => null, 'plan_id' => null, 'count' => $due];
        }
        $mathWeak = in_array('math', array_slice($readiness['weakest'], 0, Drills::TOPICS), true);
        if ($mathWeak) {
            $ranked[] = ['mode' => 'math', 'why' => 'math_weak', 'topic' => 'math', 'plan_id' => null, 'count' => null];
        }
        $ranked[] = ['mode' => 'speed', 'why' => 'quick', 'topic' => null, 'plan_id' => null, 'count' => null];
        if (! $mathWeak) {
            $ranked[] = ['mode' => 'math', 'why' => 'math', 'topic' => 'math', 'plan_id' => null, 'count' => null];
        }
        if ($diagnostic && $percent < 80 && ($percent >= 60 || ($days !== null && $days <= 45))) {
            $ranked[] = $simulation;
        }
        $ranked[] = ['mode' => 'readiness', 'why' => 'map', 'topic' => null, 'plan_id' => null, 'count' => null];
        $open = TestAttempt::query()->where('user_id', $user->id)->where('kind', TestAttempt::DRILL)->whereNull('topic')->whereNull('submitted_at')->value('id');

        return [
            'drill' => [
                'topics' => $topics, 'questions' => Drills::QUESTIONS, 'misses' => min(Drills::MISSES, count($this->drills->misses($user, $topics))),
                'open' => is_numeric($open) ? (int) $open : null,
            ],
            'plans' => array_values(array_map(static fn (TopicPlan $p): array => [
                'id' => $p->id, 'topic' => $p->topic, 'steps' => count($p->steps),
                'done' => count(array_filter($p->steps, static fn (array $s): bool => ($s['done_at'] ?? null) !== null)),
            ], $plans->all())),
            'ranked' => array_map(static fn (array $s): array => $s + ['lesson_id' => null], $ranked),
        ];
    }

    /**
     * The next Exam success lesson the candidate hasn't finished, and how many are left.
     *
     * @return array{lesson_id: int, left: int}|null
     */
    private function examSuccess(User $user): ?array
    {
        $course = Course::query()->where('slug', ExamSuccess::SLUG)->where('status', CourseStatus::Live->value)->first();
        if ($course === null) {
            return null;
        }
        $lessons = Lesson::query()->learnable()->where('course_id', $course->id)->orderBy('position')->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0);
        $done = LessonProgress::query()->where('user_id', $user->id)->whereIn('lesson_id', $lessons)->whereNotNull('completed_at')->pluck('lesson_id')->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0);
        $left = $lessons->diff($done)->values();

        return $left->isEmpty() ? null : ['lesson_id' => $left->first(), 'left' => $left->count()];
    }
}
