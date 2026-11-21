<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Enums\QuizTopic;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The free 10-question quiz before sign-up (mobile journey screens 6–7):
 * questions for the chosen state, scored on the phone at once, and saved to
 * the account when it's created.
 */
final readonly class FreeQuiz
{
    public const LENGTH = 10;

    /** @return Collection<int, QuizQuestion> */
    public function questions(State $state): Collection
    {
        return QuizQuestion::query()
            ->where('in_free_quiz', true)
            ->where(static fn ($query) => $query->whereNull('state_code')->orWhere('state_code', $state->code))
            ->inRandomOrder()
            ->limit(self::LENGTH)
            ->get();
    }

    /**
     * Score answers and keep them on the account.
     *
     * @param  list<array{question_id: int, choice: int}>  $answers
     */
    public function record(User $user, ?State $state, array $answers): QuizAttempt
    {
        $questions = QuizQuestion::query()->findMany(array_column($answers, 'question_id'))->keyBy('id');
        $scored = [];

        foreach ($answers as $answer) {
            $question = $questions->get($answer['question_id']);

            if ($question instanceof QuizQuestion) {
                $scored[] = [
                    'question_id' => $question->id,
                    'choice' => $answer['choice'],
                    'correct' => $answer['choice'] === $question->correct_option,
                ];
            }
        }

        return QuizAttempt::query()->create([
            'user_id' => $user->id,
            'kind' => 'free_quiz',
            'state_code' => $state?->code,
            'answers' => $scored,
            'correct' => count(array_filter($scored, static fn (array $a): bool => $a['correct'])),
            'total' => count($scored),
            'completed_at' => now(),
        ]);
    }

    /**
     * How each topic went: strong (75%+), learning (40%+) or start here.
     *
     * @return list<array{topic: QuizTopic, level: string}>
     */
    public function topics(QuizAttempt $attempt): array
    {
        $questions = QuizQuestion::query()->findMany(array_column($attempt->answers, 'question_id'))->keyBy('id');
        $tally = [];

        foreach ($attempt->answers as $answer) {
            $question = $questions->get($answer['question_id']);

            if ($question instanceof QuizQuestion) {
                $tally[$question->topic->value] ??= [0, 0];
                $tally[$question->topic->value][0] += $answer['correct'] ? 1 : 0;
                $tally[$question->topic->value][1]++;
            }
        }

        $topics = [];

        foreach ($tally as $topic => [$right, $total]) {
            $share = $right / max(1, $total);
            $topics[] = [
                'topic' => QuizTopic::from($topic),
                'level' => $share >= 0.75 ? 'strong' : ($share >= 0.4 ? 'learning' : 'start_here'),
            ];
        }

        return $topics;
    }
}
