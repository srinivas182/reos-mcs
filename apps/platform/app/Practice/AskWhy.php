<?php

declare(strict_types=1);

namespace App\Practice;

use App\Content\Learning\Learning;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ask why (answer review, candidate screen 45; A9.2e): opens Ask the
 * instructor in the lesson that teaches a question, at the right second,
 * with the question already asked: why is this the right answer? The
 * lesson is the one the question came from, else the passage course search
 * finds for it; only lessons the candidate can open.
 */
final readonly class AskWhy
{
    public function __construct(private Learning $learning) {}

    /** @return array{lesson_id: int, seconds: int|null, question: string}|null */
    public function for(User $user, TestAttempt $attempt, int $questionId, string $language): ?array
    {
        $question = in_array($questionId, $attempt->question_ids, true) ? QuizQuestion::query()->find($questionId) : null;

        if ($question === null) {
            return null;
        }

        $lessonId = $question->review_item_id === null ? null : DB::table('review_items')->where('id', $question->review_item_id)->value('lesson_id');
        $seconds = null;

        if (! is_numeric($lessonId)) {
            $words = array_slice(array_filter(preg_split('/[^\pL]+/u', $question->in('en')['question']) ?: [], static fn (string $w): bool => mb_strlen($w) > 4), 0, 6);
            $hit = $words === [] ? null : ($this->learning->search($user, implode(' or ', $words))[0] ?? null);
            $lessonId = $hit?->lessonId;
            $seconds = $hit?->seconds;
        }

        $lesson = is_numeric($lessonId) ? $this->learning->find($user, (int) $lessonId) : null;

        if (! $lesson instanceof Lesson || ! $this->learning->canOpen($user, $lesson)) {
            return null;
        }

        $text = $question->in($language);

        return [
            'lesson_id' => $lesson->id,
            'seconds' => $seconds,
            'question' => mb_substr(__('practice.results.whyQuestion', ['answer' => $text['options'][$question->correct_option] ?? '', 'question' => $text['question']], $language), 0, 400),
        ];
    }
}
