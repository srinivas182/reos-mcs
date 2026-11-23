<?php

declare(strict_types=1);

namespace App\Practice;

use App\Models\QuizQuestion;
use App\Models\SpeedRound;
use App\Models\User;
use App\Practice\Flashcards\Flashcards;
use App\Services\States\PersonStates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The speed round (candidate screen 50, A9.2d, ADR 0035): 60 seconds of
 * true-or-false statements, each a question with one of its options ("is
 * this the answer?"), right half the time. The server keeps the truths and
 * scores the taps; the best score is kept. Misses go into flashcards, and
 * the round feeds readiness lightly (a quarter of a test answer each).
 *
 * @phpstan-type Statement array{id: int, prompt: string, answer: string, truth: bool, topic: string}
 */
final readonly class SpeedRounds
{
    public const SECONDS = 60;

    public const STATEMENTS = 40;

    /** A speed-round answer counts this much towards readiness, against a test answer. */
    public const WEIGHT = 0.25;

    public function __construct(private PersonStates $states, private Flashcards $flashcards) {}

    /**
     * A new round: 40 statements and the best score so far.
     *
     * @return array{id: int, seconds: int, best: int|null, statements: list<Statement>}
     */
    public function start(User $user, string $language): array
    {
        $state = $this->states->current($user);
        $questions = QuizQuestion::query()->whereNull('retired_at')->where(static fn ($q) => $q->where('state_code', $state)->orWhereNull('state_code'))
            ->inRandomOrder()->limit(self::STATEMENTS)->get();

        if ($questions->count() < 10) {
            throw ValidationException::withMessages(['round' => __('practice.errors.noQuestions')]);
        }

        $statements = [];
        foreach ($questions->values() as $i => $q) {
            $options = $q->in('en')['options'];
            $truth = random_int(0, 1) === 1;
            $wrong = array_values(array_diff(array_keys($options), [$q->correct_option]));
            $statements[] = ['id' => $i + 1, 'question_id' => $q->id, 'option' => $truth || $wrong === [] ? $q->correct_option : $wrong[random_int(0, count($wrong) - 1)], 'truth' => $truth || $wrong === [], 'topic' => $q->topic->value];
        }

        $round = SpeedRound::query()->create(['user_id' => $user->id, 'statements' => $statements, 'started_at' => now()]);

        return ['id' => $round->id, 'seconds' => self::SECONDS, 'best' => $this->best($user), 'statements' => $this->show($round, $language, $questions->keyBy('id')->all())];
    }

    /**
     * Score the round from the taps (statement id: tapped True). Misses go
     * into flashcards. Once only.
     *
     * @param  array<int, bool>  $taps
     * @return array{score: int, answered: int, best: int, record: bool, misses: int}
     */
    public function finish(SpeedRound $round, array $taps, string $language): array
    {
        if ($round->finished_at !== null) {
            throw ValidationException::withMessages(['round' => __('practice.errors.ended')]);
        }

        $user = User::query()->findOrFail($round->user_id);
        $before = $this->best($user);
        $questions = QuizQuestion::query()->whereIn('id', array_column($round->statements, 'question_id'))->get()->keyBy('id');
        $score = 0;
        $answered = 0;
        $byTopic = [];
        $misses = 0;

        DB::transaction(function () use ($round, $taps, $questions, $language, $user, &$score, &$answered, &$byTopic, &$misses): void {
            foreach ($round->statements as $s) {
                if (! array_key_exists($s['id'], $taps)) {
                    continue;
                }
                $answered++;
                $right = $taps[$s['id']] === $s['truth'];
                $score += $right ? 1 : 0;
                $byTopic[$s['topic']] ??= ['correct' => 0, 'total' => 0];
                $byTopic[$s['topic']]['total']++;
                $byTopic[$s['topic']]['correct'] += $right ? 1 : 0;
                $q = $questions->get($s['question_id']);

                if (! $right && $q instanceof QuizQuestion) {
                    $text = $q->in($language);
                    $this->flashcards->save($user, 'speed', $text['question'], __('practice.speed.cardBack', ['answer' => $text['options'][$q->correct_option] ?? '', 'why' => $text['explanation']]), $language, questionId: $q->id);
                    $misses++;
                }
            }

            $round->forceFill(['answers' => $taps, 'score' => $score, 'answered' => $answered, 'by_topic' => $byTopic, 'finished_at' => now()])->save();
        });

        return ['score' => $score, 'answered' => $answered, 'best' => max($score, $before ?? 0), 'record' => $before === null || $score > $before, 'misses' => $misses];
    }

    public function best(User $user): ?int
    {
        $best = SpeedRound::query()->where('user_id', $user->id)->whereNotNull('finished_at')->max('score');

        return is_numeric($best) ? (int) $best : null;
    }

    /**
     * @param  array<int, QuizQuestion>  $questions
     * @return list<Statement>
     */
    private function show(SpeedRound $round, string $language, array $questions): array
    {
        $out = [];
        foreach ($round->statements as $s) {
            $q = $questions[$s['question_id']] ?? null;
            if ($q instanceof QuizQuestion) {
                $text = $q->in($language);
                $out[] = ['id' => $s['id'], 'prompt' => $text['question'], 'answer' => $text['options'][$s['option']] ?? '', 'truth' => $s['truth'], 'topic' => $s['topic']];
            }
        }

        return $out;
    }
}
