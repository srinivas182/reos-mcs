<?php

declare(strict_types=1);

namespace App\Content\Review;

use App\Enums\QuizTopic;
use App\Models\Course;
use App\Models\Organisation;
use App\Models\QuestionDispute;
use App\Models\QuizQuestion;
use App\Models\ReviewItem;
use App\Models\User;
use App\Notifications\QuestionDisputeResolved;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The question bank (A8.1, ADR 0031; instructor web screen 45): questions
 * drafted with a school's AI lessons are approved, edited or rejected by
 * the people who build its courses. An approved question joins the school's
 * bank with its source and exam-outline mapping (portion and topic), which
 * every question needs. Learners report questions; the owner keeps or
 * corrects them, and the learner is told.
 *
 * @phpstan-type Edit array{en?: array{prompt: string, options: list<string>, explanation: string}, es?: array{prompt: string, options: list<string>, explanation: string}, correct?: int, topic?: string, difficulty?: string, portion?: string}
 */
final readonly class QuestionBank
{
    public const PORTIONS = ['national', 'state'];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * Questions on one tab, for the school's courses (instructors: the courses they teach).
     *
     * @param  list<int>  $courseIds
     * @return Collection<int, ReviewItem>|Collection<int, QuestionDispute>
     */
    public function tab(array $courseIds, string $tab): Collection
    {
        if ($tab === 'disputed') {
            return QuestionDispute::query()->where('status', 'open')
                ->whereHas('question', static fn ($q) => $q->whereIn('course_id', $courseIds))
                ->with(['question', 'user'])->orderBy('created_at')->get();
        }

        return ReviewItem::query()->where('kind', 'question')
            ->where('status', match ($tab) {
                'approved' => ReviewItem::APPROVED,
                'rejected' => ReviewItem::REJECTED,
                default => ReviewItem::TO_REVIEW,
            })
            ->whereHas('lesson', static fn ($q) => $q->whereIn('course_id', $courseIds))
            ->with('lesson.course')->orderBy('id')->get();
    }

    /**
     * @param  list<int>  $courseIds
     * @return array{to_review: int, approved: int, rejected: int, disputed: int}
     */
    public function counts(array $courseIds): array
    {
        $base = ReviewItem::query()->where('kind', 'question')->whereHas('lesson', static fn ($q) => $q->whereIn('course_id', $courseIds));

        return [
            'to_review' => (clone $base)->where('status', ReviewItem::TO_REVIEW)->count(),
            'approved' => (clone $base)->where('status', ReviewItem::APPROVED)->count(),
            'rejected' => (clone $base)->where('status', ReviewItem::REJECTED)->count(),
            'disputed' => QuestionDispute::query()->where('status', 'open')->whereHas('question', static fn ($q) => $q->whereIn('course_id', $courseIds))->count(),
        ];
    }

    /**
     * Edits a question: wording in either language, the right answer, topic, difficulty and portion.
     *
     * @param  Edit  $edit
     */
    public function edit(ReviewItem $item, array $edit, User $actor): ReviewItem
    {
        $content = $item->content;

        foreach (['en', 'es'] as $language) {
            if (isset($edit[$language])) {
                $content[$language] = ['prompt' => trim($edit[$language]['prompt']), 'options' => array_map(trim(...), $edit[$language]['options']), 'explanation' => trim($edit[$language]['explanation'])];
            }
        }

        $english = is_array($content['en'] ?? null) ? $content['en'] : [];
        $options = is_array($english['options'] ?? null) ? count($english['options']) : 0;
        if (isset($edit['correct'])) {
            if ($edit['correct'] < 0 || $edit['correct'] >= $options) {
                throw ValidationException::withMessages(['correct' => __('review.errors.correct')]);
            }
            $content['correct'] = $edit['correct'];
        }

        $item->forceFill([
            'content' => $content,
            'topic' => $edit['topic'] ?? $item->topic,
            'difficulty' => $edit['difficulty'] ?? $item->difficulty,
            'portion' => $edit['portion'] ?? $item->portion,
        ])->save();

        if ($item->status === ReviewItem::APPROVED) {
            $this->sync($item);
        }

        $this->audit->record('question.edited', 'review_item', (string) $item->id, [], $actor, $item->lesson->course->organisation_id);

        return $item;
    }

    /** Approves a question: it joins the school's bank. Needs a source and an outline mapping. */
    public function approve(ReviewItem $item, User $actor): QuizQuestion
    {
        $source = $item->content['source'] ?? null;

        if (! is_string($source) || $source === '' || QuizTopic::tryFrom((string) $item->topic) === null || ! in_array($item->portion, self::PORTIONS, true)) {
            throw ValidationException::withMessages(['item' => __('review.errors.mapping')]);
        }

        return DB::transaction(function () use ($item, $actor): QuizQuestion {
            $item->forceFill(['status' => ReviewItem::APPROVED, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
            $question = $this->sync($item);
            $this->audit->record('question.approved', 'review_item', (string) $item->id, ['question' => $question->id], $actor, $item->lesson->course->organisation_id);

            return $question;
        });
    }

    public function reject(ReviewItem $item, User $actor): void
    {
        $item->forceFill(['status' => ReviewItem::REJECTED, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
        QuizQuestion::query()->where('review_item_id', $item->id)->update(['retired_at' => now()]);
        $this->audit->record('question.rejected', 'review_item', (string) $item->id, [], $actor, $item->lesson->course->organisation_id);
    }

    /** A learner reports a question. One open report per person and question. */
    public function dispute(QuizQuestion $question, User $user, string $reason, ?string $details): QuestionDispute
    {
        if (! in_array($reason, QuestionDispute::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __('review.errors.reason')]);
        }

        return QuestionDispute::query()->firstOrCreate(
            ['quiz_question_id' => $question->id, 'user_id' => $user->id, 'status' => 'open'],
            ['reason' => $reason, 'details' => $details === null ? null : mb_substr(trim($details), 0, 2000)],
        );
    }

    /**
     * Keeps or corrects a disputed question; every open report on it is closed and its learners are told.
     *
     * @param  Edit|null  $edit
     */
    public function resolve(QuestionDispute $dispute, bool $correct, string $resolution, User $actor, ?array $edit = null): void
    {
        $question = $dispute->question;
        $item = $question->review_item_id === null ? null : ReviewItem::query()->find($question->review_item_id);

        if ($correct && $item !== null && $edit !== null) {
            $this->edit($item, $edit, $actor);
        }

        $open = QuestionDispute::query()->where('quiz_question_id', $question->id)->where('status', 'open')->with('user')->get();

        foreach ($open as $each) {
            $each->forceFill(['status' => $correct ? 'corrected' : 'kept', 'resolution' => mb_substr(trim($resolution), 0, 2000), 'resolved_by' => $actor->id, 'resolved_at' => now()])->save();
            $each->user->notify(new QuestionDisputeResolved($each));
        }

        $this->audit->record($correct ? 'question.corrected' : 'question.kept', 'quiz_question', (string) $question->id, ['reports' => $open->count()], $actor, $question->organisation_id);
    }

    /**
     * The courses a person reviews questions for: all the school's, or those they teach.
     *
     * @return list<int>
     */
    public static function courseIds(Organisation $school, ?User $instructor): array
    {
        return array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, Course::query()->where('organisation_id', $school->id)
            ->when($instructor !== null, static fn ($q) => $q->whereHas('instructors', static fn ($i) => $i->where('users.id', $instructor?->id)))
            ->pluck('id')->all()));
    }

    /** The school's bank copy of an approved question. */
    private function sync(ReviewItem $item): QuizQuestion
    {
        $course = $item->lesson->course;
        $content = $item->content;
        $page = $content['page'] ?? null;
        $source = (is_string($content['source'] ?? null) ? $content['source'] : '').(is_int($page) ? ', p. '.$page : '');
        $languages = [];

        foreach (['en', 'es'] as $language) {
            $c = $content[$language] ?? null;
            if (is_array($c)) {
                $languages[$language] = [
                    'question' => is_string($c['prompt'] ?? null) ? $c['prompt'] : '',
                    'options' => array_values(array_filter((array) ($c['options'] ?? []), is_string(...))),
                    'explanation' => is_string($c['explanation'] ?? null) ? $c['explanation'] : '',
                ];
            }
        }

        return QuizQuestion::query()->updateOrCreate(['review_item_id' => $item->id], [
            'organisation_id' => $course->organisation_id, 'course_id' => $course->id, 'state_code' => 'TX',
            'topic' => QuizTopic::tryFrom((string) $item->topic) ?? QuizTopic::StateRules, 'difficulty' => $item->difficulty, 'portion' => $item->portion,
            'content' => $languages, 'correct_option' => is_int($content['correct'] ?? null) ? $content['correct'] : 0,
            'source' => mb_substr($source, 0, 250), 'is_sample' => false, 'in_free_quiz' => false, 'retired_at' => null,
        ]);
    }
}
