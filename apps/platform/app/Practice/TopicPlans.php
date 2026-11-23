<?php

declare(strict_types=1);

namespace App\Practice;

use App\Content\Learning\Learning;
use App\Models\Flashcard;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use App\Models\TestAttempt;
use App\Models\TopicPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fix a weak topic (candidate screen 46, A9.2b, ADR 0035): a short plan of
 * 4 to 6 steps built from the candidate's own misses on the topic.
 *
 * - **Micro-lessons** (up to two): the lesson behind a missed question, at
 *   the right second: the lesson the question came from, else the passage
 *   course search finds for it.
 * - **Your misses, with why**: the recent misses on the topic.
 * - **Practice**: a 10-question topic drill, misses first.
 * - **Retest**: 10 questions not seen yet. Finishing it completes the plan.
 *
 * - **Flashcards** (A9.2d): a two-minute burst of the topic's cards, when
 *   the candidate has some.
 *
 * Short plans get a second practice round to reach four steps. Lessons, the
 * misses and flashcards are done once opened; practice and the retest once
 * submitted. The topic leaves the weak list when readiness reaches 70%
 * (Tests::PASS).
 *
 * @phpstan-type Step array{n: int, kind: string, minutes: int, status: 'done'|'current'|'todo', lesson: array{id: int, title: string, seconds: int|null}|null, attempt_id: int|null, results_id: int|null}
 * @phpstan-type Plan array{id: int, topic: string, now: int|null, start: int|null, goal: int, weak: bool, minutes: int, completed: bool, steps: list<Step>, misses: list<array{id: int, prompt: string, options: list<string>, right: int, explanation: string, source: string}>}
 */
final readonly class TopicPlans
{
    public const LESSON_MINUTES = 5;

    public const REVIEW_MINUTES = 4;

    public const PRACTICE_MINUTES = 10;

    public const RETEST_MINUTES = 8;

    /** A two-minute flashcard burst (A9.2d). */
    public const FLASHCARD_MINUTES = 2;

    public function __construct(private Readiness $readiness, private Drills $drills, private Learning $learning, private Tests $tests) {}

    /** The candidate's plan for the topic still in progress, or a new one. */
    public function for(User $user, string $topic): TopicPlan
    {
        if (! array_key_exists($topic, DiagnosticBank::WEIGHTS)) {
            throw ValidationException::withMessages(['topic' => __('practice.errors.noQuestions')]);
        }

        $open = TopicPlan::query()->where('user_id', $user->id)->where('topic', $topic)->whereNull('completed_at')->latest('id')->first();

        if ($open !== null) {
            return $open;
        }

        $misses = array_slice($this->drills->misses($user, [$topic]), 0, 8);
        $steps = [];

        foreach ($this->lessons($user, $misses) as $lesson) {
            $steps[] = ['kind' => 'lesson', 'minutes' => self::LESSON_MINUTES, 'lesson_id' => $lesson['id'], 'title' => $lesson['title'], 'seconds' => $lesson['seconds'], 'done_at' => null];
        }

        if ($misses !== []) {
            $steps[] = ['kind' => 'review', 'minutes' => self::REVIEW_MINUTES, 'questions' => $misses, 'done_at' => null];
        }

        $steps[] = ['kind' => 'practice', 'minutes' => self::PRACTICE_MINUTES, 'attempt_id' => null, 'done_at' => null];
        if (count($steps) < 3) {
            $steps[] = ['kind' => 'practice', 'minutes' => self::PRACTICE_MINUTES, 'attempt_id' => null, 'done_at' => null];
        }
        if (Flashcard::query()->where('user_id', $user->id)->where('topic', $topic)->exists()) {
            $steps[] = ['kind' => 'flashcards', 'minutes' => self::FLASHCARD_MINUTES, 'done_at' => null];
        }
        $steps[] = ['kind' => 'retest', 'minutes' => self::RETEST_MINUTES, 'attempt_id' => null, 'done_at' => null];

        return TopicPlan::query()->create([
            'user_id' => $user->id, 'topic' => $topic, 'start_percent' => $this->readiness->of($user)['topics'][$topic] ?? null, 'steps' => $steps,
        ]);
    }

    /**
     * The plan as screen 46 shows it: now against the goal, every step with
     * its minutes and whether it's done, and the misses with why.
     *
     * @return Plan
     */
    public function show(TopicPlan $plan, string $language): array
    {
        $this->settle($plan);
        $readiness = $this->readiness->of(User::query()->findOrFail($plan->user_id));
        $current = $this->current($plan);
        $steps = [];

        foreach ($plan->steps as $n => $step) {
            $attempt = isset($step['attempt_id']) ? TestAttempt::query()->find($step['attempt_id']) : null;
            $steps[] = [
                'n' => $n + 1, 'kind' => $step['kind'], 'minutes' => $step['minutes'],
                'status' => ($step['done_at'] ?? null) !== null ? 'done' : ($n === $current ? 'current' : 'todo'),
                'lesson' => isset($step['lesson_id']) ? ['id' => (int) $step['lesson_id'], 'title' => (string) ($step['title'] ?? ''), 'seconds' => $step['seconds'] ?? null] : null,
                'attempt_id' => $attempt?->id,
                'results_id' => $attempt?->submitted_at !== null ? $attempt->id : null,
            ];
        }

        $missIds = [];
        foreach ($plan->steps as $step) {
            $missIds = [...$missIds, ...($step['questions'] ?? [])];
        }

        return [
            'id' => $plan->id, 'topic' => $plan->topic, 'now' => $readiness['topics'][$plan->topic] ?? null, 'start' => $plan->start_percent, 'goal' => Tests::PASS,
            'weak' => in_array($plan->topic, $readiness['weakest'], true),
            'minutes' => array_sum(array_map(static fn (array $s): int => $s['minutes'], array_filter($steps, static fn (array $s): bool => $s['status'] !== 'done'))),
            'completed' => $plan->completed_at !== null,
            'steps' => $steps,
            'misses' => $this->missesWithWhy($missIds, $language),
        ];
    }

    /**
     * Continue: starts the next step and says where to go. A lesson or the
     * misses are done once opened; practice and the retest start (or carry
     * on with) their test.
     *
     * @return array{kind: string, lesson: array{id: int, seconds: int|null}|null, attempt_id: int|null}
     */
    public function continue(TopicPlan $plan): array
    {
        $this->settle($plan);
        $n = $this->current($plan);

        if ($n === null) {
            throw ValidationException::withMessages(['plan' => __('practice.errors.planDone')]);
        }

        return DB::transaction(function () use ($plan, $n): array {
            $locked = TopicPlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $steps = $locked->steps;
            $step = $steps[$n];
            $user = User::query()->findOrFail($locked->user_id);
            $out = ['kind' => $step['kind'], 'lesson' => null, 'attempt_id' => null];

            if (in_array($step['kind'], ['lesson', 'review', 'flashcards'], true)) {
                $steps[$n]['done_at'] = now()->toIso8601String();
                $out['lesson'] = isset($step['lesson_id']) ? ['id' => (int) $step['lesson_id'], 'seconds' => $step['seconds'] ?? null] : null;
            } else {
                $attempt = isset($step['attempt_id']) ? TestAttempt::query()->find($step['attempt_id']) : null;
                if ($attempt === null || $attempt->submitted_at !== null) {
                    $attempt = $step['kind'] === 'retest'
                        ? $this->tests->start($user, TestAttempt::RETEST, $locked->topic)
                        : $this->tests->start($user, TestAttempt::DRILL, $locked->topic);
                }
                $steps[$n]['attempt_id'] = $attempt->id;
                $out['attempt_id'] = $attempt->id;
            }

            $locked->forceFill(['steps' => $steps])->save();
            $plan->setRawAttributes($locked->getAttributes(), true);

            return $out;
        });
    }

    /** Marks practice and retest steps done once their test is submitted, and completes the plan after the retest. */
    private function settle(TopicPlan $plan): void
    {
        $steps = $plan->steps;
        $changed = false;

        foreach ($steps as $n => $step) {
            if (($step['done_at'] ?? null) === null && isset($step['attempt_id'])) {
                $submitted = TestAttempt::query()->whereKey($step['attempt_id'])->value('submitted_at');
                if ($submitted !== null) {
                    $steps[$n]['done_at'] = now()->toIso8601String();
                    $changed = true;
                }
            }
        }

        $last = $steps[array_key_last($steps)] ?? null;
        $complete = $plan->completed_at === null && $last !== null && $last['kind'] === 'retest' && ($last['done_at'] ?? null) !== null;

        if ($changed || $complete) {
            $plan->forceFill(['steps' => $steps, 'completed_at' => $complete ? now() : $plan->completed_at])->save();
        }
    }

    private function current(TopicPlan $plan): ?int
    {
        foreach ($plan->steps as $n => $step) {
            if (($step['done_at'] ?? null) === null) {
                return $n;
            }
        }

        return null;
    }

    /**
     * Up to two lessons behind these misses, at the right second: the
     * lesson a question came from, else the passage course search finds.
     *
     * @param  list<int>  $misses
     * @return list<array{id: int, title: string, seconds: int|null}>
     */
    private function lessons(User $user, array $misses): array
    {
        $found = [];

        foreach (QuizQuestion::query()->whereIn('id', $misses)->get() as $question) {
            if (count($found) >= 2) {
                break;
            }

            $lessonId = $question->review_item_id === null ? null : DB::table('review_items')->where('id', $question->review_item_id)->value('lesson_id');
            $seconds = null;

            if (! is_numeric($lessonId)) {
                $words = array_slice(array_filter(preg_split('/[^\pL]+/u', $question->in('en')['question']) ?: [], static fn (string $w): bool => mb_strlen($w) > 4), 0, 6);
                $hit = $words === [] ? null : ($this->learning->search($user, implode(' or ', $words), 'en')[0] ?? null);
                $lessonId = $hit?->lessonId;
                $seconds = $hit?->seconds;
            }

            $lesson = is_numeric($lessonId) ? $this->learning->find($user, (int) $lessonId) : null;

            if ($lesson instanceof Lesson && ! isset($found[$lesson->id]) && $this->learning->canOpen($user, $lesson)) {
                $found[$lesson->id] = ['id' => $lesson->id, 'title' => $lesson->title, 'seconds' => $seconds];
            }
        }

        return array_values($found);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, prompt: string, options: list<string>, right: int, explanation: string, source: string}>
     */
    private function missesWithWhy(array $ids, string $language): array
    {
        $questions = QuizQuestion::query()->whereIn('id', $ids)->get()->keyBy('id');
        $out = [];

        foreach ($ids as $id) {
            $q = $questions->get($id);
            if ($q instanceof QuizQuestion) {
                $text = $q->in($language);
                $out[] = ['id' => $q->id, 'prompt' => $text['question'], 'options' => $text['options'], 'right' => $q->correct_option, 'explanation' => $text['explanation'], 'source' => $q->source];
            }
        }

        return $out;
    }
}
