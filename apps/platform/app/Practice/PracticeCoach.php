<?php

declare(strict_types=1);

namespace App\Practice;

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
 * - A full simulation: first when readiness is 80% or more (rehearse before
 *   booking), otherwise after practice once readiness reaches 60% or the
 *   exam is 45 days away.
 * - The readiness map.
 *
 * Flashcards, the speed round, math lab, hands-free and challenges join
 * the ranking as they arrive (A9.2c to A9.3).
 *
 * @phpstan-type Suggestion array{mode: 'diagnostic'|'plan'|'drill'|'fix'|'simulation'|'readiness', why: string, topic: string|null, plan_id: int|null}
 */
final readonly class PracticeCoach
{
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
            $ranked[] = ['mode' => 'diagnostic', 'why' => 'baseline', 'topic' => null, 'plan_id' => null];
        }
        $simulation = ['mode' => 'simulation', 'why' => $percent >= 80 ? 'rehearse' : ($days !== null && $days <= 45 ? 'exam_soon' : 'check'), 'topic' => null, 'plan_id' => null];
        if ($diagnostic && $percent >= 80) {
            $ranked[] = $simulation;
        }
        foreach ($plans as $plan) {
            $ranked[] = ['mode' => 'plan', 'why' => 'carry_on', 'topic' => $plan->topic, 'plan_id' => $plan->id];
        }
        $ranked[] = ['mode' => 'drill', 'why' => 'weakest', 'topic' => null, 'plan_id' => null];
        if ($weakest !== null && ! $plans->contains('topic', $weakest)) {
            $ranked[] = ['mode' => 'fix', 'why' => 'weakest', 'topic' => $weakest, 'plan_id' => null];
        }
        if ($diagnostic && $percent < 80 && ($percent >= 60 || ($days !== null && $days <= 45))) {
            $ranked[] = $simulation;
        }
        $ranked[] = ['mode' => 'readiness', 'why' => 'map', 'topic' => null, 'plan_id' => null];
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
            'ranked' => $ranked,
        ];
    }
}
