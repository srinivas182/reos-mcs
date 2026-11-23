<?php

declare(strict_types=1);

namespace App\Practice;

use App\Models\QuizQuestion;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Smart drills and retests (A9.2b, ADR 0035; candidate screen 47).
 *
 * The smart drill is 15 questions: up to five recent misses (answered wrong
 * in the last 30 days and not right since), the rest from the three weakest
 * topics, questions not seen yet first. A topic drill (a fix plan's practice
 * step) is 10 on one topic, misses first. A retest is 10 new questions on
 * one topic. A drill's Hint strikes out one wrong option: a nudge, never the
 * answer; hints are kept for challenges (A9.3), where they cost points.
 */
final readonly class Drills
{
    public const QUESTIONS = 15;

    public const TOPIC_QUESTIONS = 10;

    public const MISSES = 5;

    /** The weakest topics a smart drill covers. */
    public const TOPICS = 3;

    public function __construct(private Readiness $readiness) {}

    /**
     * The weakest topics, by readiness: those below the pass line first,
     * then the lowest shares, then untested ones by outline weight.
     *
     * @return list<string>
     */
    public function weakestTopics(User $user, int $count = self::TOPICS): array
    {
        $readiness = $this->readiness->of($user);
        $order = array_keys(DiagnosticBank::WEIGHTS);
        $rest = array_values(array_diff($order, $readiness['weakest']));
        usort($rest, static fn (string $a, string $b): int => [$readiness['topics'][$a] === null ? 1 : 0, $readiness['topics'][$a] ?? 0, -DiagnosticBank::WEIGHTS[$a]]
            <=> [$readiness['topics'][$b] === null ? 1 : 0, $readiness['topics'][$b] ?? 0, -DiagnosticBank::WEIGHTS[$b]]);

        return array_slice([...$readiness['weakest'], ...$rest], 0, $count);
    }

    /**
     * Questions for a smart drill (no topic) or a topic drill.
     *
     * @return list<int>
     */
    public function drill(User $user, string $state, ?string $topic): array
    {
        $topics = $topic === null ? $this->weakestTopics($user) : [$topic];
        $wanted = $topic === null ? self::QUESTIONS : self::TOPIC_QUESTIONS;
        $misses = array_slice($this->misses($user, $topics), 0, self::MISSES);
        $seen = $this->seen($user);
        $pool = $this->pool($state, $topics)->reject(static fn (QuizQuestion $q): bool => in_array($q->id, $misses, true));
        $picked = $misses;
        $per = (int) ceil(($wanted - count($picked)) / max(1, count($topics)));

        foreach ($topics as $t) {
            foreach ($pool->filter(static fn (QuizQuestion $q): bool => $q->topic->value === $t)->sortBy(static fn (QuizQuestion $q): int => $seen[$q->id] ?? 0)->take($per) as $q) {
                $picked[] = $q->id;
            }
        }

        $picked = array_slice(array_values(array_unique($picked)), 0, $wanted);
        // Short on a topic: make up the count from the others.
        $extra = $pool->reject(static fn (QuizQuestion $q): bool => in_array($q->id, $picked, true))->sortBy(static fn (QuizQuestion $q): int => $seen[$q->id] ?? 0)
            ->take($wanted - count($picked))->pluck('id')->all();

        return self::enough(collect([...$picked, ...$extra])->shuffle());
    }

    /**
     * A retest: questions on the topic never seen, then the longest ago.
     *
     * @return list<int>
     */
    public function retest(User $user, string $state, string $topic): array
    {
        $seen = $this->seen($user);

        return self::enough($this->pool($state, [$topic])->shuffle()->sortBy(static fn (QuizQuestion $q): int => $seen[$q->id] ?? 0)
            ->take(self::TOPIC_QUESTIONS)->pluck('id')->shuffle());
    }

    /**
     * Recent misses on these topics: answered wrong in a test in the last 30
     * days and not answered right since, most recent first.
     *
     * @param  list<string>  $topics
     * @return list<int>
     */
    public function misses(User $user, array $topics): array
    {
        $attempts = TestAttempt::query()->where('user_id', $user->id)->whereNotNull('submitted_at')->where('submitted_at', '>=', now()->subDays(30))
            ->oldest('submitted_at')->get(['question_ids', 'answers', 'unscored']);
        $ids = $attempts->flatMap(static fn (TestAttempt $a): array => $a->question_ids)->unique()->all();
        $questions = QuizQuestion::query()->whereIn('id', $ids)->whereIn('topic', $topics)->whereNull('retired_at')->pluck('correct_option', 'id');
        $missed = [];

        foreach ($attempts as $attempt) {
            foreach ($attempt->question_ids as $id) {
                if (! $questions->has($id)) {
                    continue;
                }
                $choice = $attempt->answers[$id] ?? null;
                unset($missed[$id]);
                if ($choice !== $questions[$id]) {
                    $missed[$id] = true;
                }
            }
        }

        return array_reverse(array_map('intval', array_keys($missed)));
    }

    /**
     * Hint (smart drill only): one wrong option to strike out, the same each
     * time for this question in this drill. Kept for challenges.
     *
     * @return array{eliminated: int}
     *
     * @throws ValidationException outside a drill or a challenge, or once answered
     */
    public function hint(TestAttempt $attempt, int $question): array
    {
        $found = QuizQuestion::query()->find($question);

        if (! in_array($attempt->kind, [TestAttempt::DRILL, TestAttempt::CHALLENGE], true) || $attempt->submitted_at !== null || $found === null || ! in_array($question, $attempt->question_ids, true) || isset(($attempt->answers ?? [])[$question])) {
            throw ValidationException::withMessages(['question' => __('practice.errors.noHint')]);
        }

        $wrong = array_values(array_diff(array_keys($found->in('en')['options']), [$found->correct_option]));

        DB::transaction(function () use ($attempt, $question): void {
            $locked = TestAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $hints = $locked->hints ?? [];
            $locked->forceFill(['hints' => array_values(array_unique([...$hints, $question]))])->save();
        });

        return ['eliminated' => $wrong[($question + $attempt->id) % max(1, count($wrong))] ?? 0];
    }

    /**
     * Questions on these topics a candidate in this state can get, samples included.
     *
     * @param  list<string>  $topics
     * @return Collection<int, QuizQuestion>
     */
    private function pool(string $state, array $topics): Collection
    {
        return QuizQuestion::query()->whereIn('topic', $topics)->whereNull('retired_at')
            ->where(static fn ($q) => $q->where('state_code', $state)->orWhereNull('state_code'))->get(['id', 'topic'])->shuffle()->values();
    }

    /**
     * How often each question has been asked to this person (question id: times).
     *
     * @return array<int, int>
     */
    private function seen(User $user): array
    {
        $seen = [];

        foreach (TestAttempt::query()->where('user_id', $user->id)->pluck('question_ids') as $ids) {
            foreach (is_array($ids) ? $ids : [] as $id) {
                if (is_int($id)) {
                    $seen[$id] = ($seen[$id] ?? 0) + 1;
                }
            }
        }

        return $seen;
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return list<int>
     *
     * @throws ValidationException with too few questions to practise
     */
    private static function enough(Collection $ids): array
    {
        $ids = array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $ids->all()));

        if (count($ids) < 5) {
            throw ValidationException::withMessages(['kind' => __('practice.errors.noQuestions')]);
        }

        return $ids;
    }
}
