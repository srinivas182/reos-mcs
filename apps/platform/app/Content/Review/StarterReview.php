<?php

declare(strict_types=1);

namespace App\Content\Review;

use App\Enums\QuizTopic;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Practice\StarterBank;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reviewing REOS's Texas starter bank (A9.1, decision 2) on the question
 * bank's Starter bank tab (screen 45). Each question counts in tests as a
 * sample until a school admin approves it (it then counts everywhere,
 * including topic quizzes) or retires it (it leaves every test). Edits keep
 * the question's status. Every decision is audited and can be undone.
 */
final readonly class StarterReview
{
    public const APPROVE = 'approve';

    public const RETIRE = 'retire';

    /** Undo: back to a sample, to review. */
    public const RESTORE = 'restore';

    public const DECISIONS = [self::APPROVE, self::RETIRE, self::RESTORE];

    public function __construct(private AuditLogger $audit) {}

    /** @return Builder<QuizQuestion> */
    public static function query(): Builder
    {
        return QuizQuestion::query()->where('key', 'like', StarterBank::PREFIX.'%');
    }

    /**
     * Questions still to review, for one topic or all, in the order written.
     *
     * @return Collection<int, QuizQuestion>
     */
    public function toReview(?QuizTopic $topic): Collection
    {
        return self::query()->where('is_sample', true)->whereNull('retired_at')
            ->when($topic !== null, static fn (Builder $q) => $q->where('topic', $topic?->value))
            ->orderBy('id')->get();
    }

    /** @return array{to_review: int, approved: int, retired: int, topics: array<string, int>} */
    public function counts(): array
    {
        $rows = self::query()->get(['topic', 'is_sample', 'retired_at']);
        $open = $rows->filter(static fn (QuizQuestion $q): bool => $q->is_sample && $q->retired_at === null);
        $topics = [];

        foreach (QuizTopic::cases() as $topic) {
            $topics[$topic->value] = $open->filter(static fn (QuizQuestion $q): bool => $q->topic === $topic)->count();
        }

        return [
            'to_review' => $open->count(),
            'approved' => $rows->filter(static fn (QuizQuestion $q): bool => ! $q->is_sample && $q->retired_at === null)->count(),
            'retired' => $rows->filter(static fn (QuizQuestion $q): bool => $q->retired_at !== null)->count(),
            'topics' => $topics,
        ];
    }

    /**
     * Approves, retires or restores starter questions; ids outside the starter bank are ignored.
     *
     * @param  list<int>  $ids
     * @return int how many changed
     */
    public function decide(array $ids, string $decision, User $actor, ?int $organisationId): int
    {
        return DB::transaction(function () use ($ids, $decision, $actor, $organisationId): int {
            $questions = self::query()->whereIn('id', $ids)->lockForUpdate()->get();

            foreach ($questions as $question) {
                $question->forceFill(match ($decision) {
                    self::APPROVE => ['is_sample' => false, 'retired_at' => null],
                    self::RETIRE => ['retired_at' => now()],
                    default => ['is_sample' => true, 'retired_at' => null],
                })->save();
            }

            if ($questions->isNotEmpty()) {
                $this->audit->record('starter_question.'.$decision, 'quiz_question', $questions->count() === 1 ? (string) $questions->value('id') : 'batch', ['ids' => $questions->pluck('id')->all()], $actor, $organisationId);
            }

            return $questions->count();
        });
    }

    /**
     * Edits a starter question's wording, right answer, topic, difficulty or portion. Its status stays.
     *
     * @param  array{en: array{prompt: string, options: list<string>, explanation: string}, es: array{prompt: string, options: list<string>, explanation: string}, correct: int, topic: string, difficulty: string, portion: string}  $edit
     */
    public function edit(QuizQuestion $question, array $edit, User $actor, ?int $organisationId): QuizQuestion
    {
        if ($edit['correct'] >= count($edit['en']['options']) || count($edit['en']['options']) !== count($edit['es']['options'])) {
            throw ValidationException::withMessages(['correct' => __('review.errors.correct')]);
        }

        $language = static fn (array $l): array => ['question' => trim($l['prompt']), 'options' => array_map(trim(...), $l['options']), 'explanation' => trim($l['explanation'])];
        $question->forceFill([
            'content' => ['en' => $language($edit['en']), 'es' => $language($edit['es'])],
            'correct_option' => $edit['correct'],
            'topic' => $edit['topic'],
            'difficulty' => $edit['difficulty'],
            'portion' => $edit['portion'],
        ])->save();
        $this->audit->record('starter_question.edited', 'quiz_question', (string) $question->id, [], $actor, $organisationId);

        return $question;
    }
}
