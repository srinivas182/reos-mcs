<?php

declare(strict_types=1);

namespace App\Http\Controllers\Builder;

use App\Authorization\Role;
use App\Content\Builder\CourseBuilder;
use App\Content\Review\QuestionBank;
use App\Content\Review\StarterReview;
use App\Enums\QuizTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Builder\QuestionEditRequest;
use App\Models\QuestionDispute;
use App\Models\QuizQuestion;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Question bank (instructor web screen 45, A8.1, ADR 0031): to review,
 * approved, rejected and disputed; approve, edit, reject; resolve learner
 * disputes. School admins see every course of the school; instructors the
 * courses they teach. School admins also get the Starter bank tab (A9.1):
 * REOS's Texas starter questions to approve, edit or retire, by topic.
 */
final class QuestionBankController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, QuestionBank $bank, StarterReview $starter): Response
    {
        $courses = $this->courses($user, $roles, $builder);
        $admin = $roles->for($user)?->role === Role::SchoolAdmin;
        $asked = $request->query('tab');
        $tabs = ['to_review', 'approved', 'rejected', 'disputed', ...($admin ? ['starter'] : [])];
        $tab = is_string($asked) && in_array($asked, $tabs, true) ? $asked : 'to_review';
        $rows = $tab === 'starter' ? collect() : $bank->tab($courses, $tab);
        $topic = QuizTopic::tryFrom(is_string($request->query('topic')) ? $request->query('topic') : '');
        $starterCounts = $admin ? $starter->counts() : null;

        return Inertia::render('builder/questions', [
            'tab' => $tab,
            'counts' => [...$bank->counts($courses), ...($starterCounts !== null ? ['starter' => $starterCounts['to_review']] : [])],
            'starter' => $tab !== 'starter' || $starterCounts === null ? null : [
                'topic' => $topic?->value,
                'counts' => $starterCounts,
                'questions' => $starter->toReview($topic)->map(static fn (QuizQuestion $q): array => self::starterRow($q))->values()->all(),
            ],
            'questions' => $tab === 'disputed' ? [] : $rows->map(static fn ($item): array => $item instanceof ReviewItem ? [
                'id' => $item->id, 'course' => $item->lesson->course->title, 'lesson' => $item->lesson->title, 'topic' => $item->topic,
                'difficulty' => $item->difficulty, 'portion' => $item->portion, 'status' => $item->status,
                'source' => trim((is_string($item->content['source'] ?? null) ? $item->content['source'] : '').(is_int($item->content['page'] ?? null) ? ', p. '.$item->content['page'] : '')),
                'en' => $item->content['en'] ?? null, 'es' => $item->content['es'] ?? null, 'correct' => $item->content['correct'] ?? 0,
            ] : [])->values()->all(),
            'disputes' => $tab !== 'disputed' ? [] : $rows->map(static fn ($d): array => $d instanceof QuestionDispute ? [
                'id' => $d->id, 'reason' => $d->reason, 'details' => $d->details, 'by' => (string) $d->user->name, 'at' => $d->created_at?->toIso8601String(),
                'question' => ['id' => $d->question->id, 'text' => $d->question->in('en')['question'], 'options' => $d->question->in('en')['options'], 'correct' => $d->question->correct_option, 'source' => $d->question->source, 'review_item_id' => $d->question->review_item_id],
            ] : [])->values()->all(),
            'topics' => array_map(static fn (QuizTopic $t): string => $t->value, QuizTopic::cases()),
            'portions' => QuestionBank::PORTIONS,
            'difficulties' => QuestionBank::DIFFICULTIES,
        ]);
    }

    public function update(QuestionEditRequest $request, ReviewItem $item, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, QuestionBank $bank): RedirectResponse
    {
        $this->owns($item, $user, $roles, $builder);
        $bank->edit($item, $request->edit(), $user);

        return back()->with('success', __('review.questionSaved'));
    }

    public function decide(Request $request, ReviewItem $item, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, QuestionBank $bank): RedirectResponse
    {
        $this->owns($item, $user, $roles, $builder);
        $decision = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]])['decision'];
        $decision === 'approve' ? $bank->approve($item, $user) : $bank->reject($item, $user);

        return back();
    }

    public function resolve(Request $request, QuestionDispute $dispute, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, QuestionBank $bank): RedirectResponse
    {
        abort_unless(in_array($dispute->question->course_id, $this->courses($user, $roles, $builder), true), 404);
        $data = $request->validate(['outcome' => ['required', Rule::in(['keep', 'correct'])], 'resolution' => ['required', 'string', 'min:5', 'max:2000']]);
        $bank->resolve($dispute, $data['outcome'] === 'correct', (string) $data['resolution'], $user);

        return back()->with('success', __('review.disputeResolved'));
    }

    /**
     * A starter question as a row like the bank's own (screen 45).
     *
     * @return array<string, mixed>
     */
    private static function starterRow(QuizQuestion $q): array
    {
        $language = static fn (string $l): array => ['prompt' => $q->in($l)['question'], 'options' => $q->in($l)['options'], 'explanation' => $q->in($l)['explanation']];

        return [
            'id' => $q->id, 'course' => '', 'lesson' => '', 'topic' => $q->topic->value, 'difficulty' => $q->difficulty, 'portion' => $q->portion,
            'status' => 'to_review', 'source' => $q->source, 'en' => $language('en'), 'es' => $language('es'), 'correct' => $q->correct_option,
        ];
    }

    private function owns(ReviewItem $item, User $user, ActiveRole $roles, CourseBuilder $builder): void
    {
        abort_unless($item->kind === 'question' && in_array($item->lesson->course_id, $this->courses($user, $roles, $builder), true), 404);
    }

    /** @return list<int> */
    private function courses(User $user, ActiveRole $roles, CourseBuilder $builder): array
    {
        $membership = $roles->for($user);
        $school = $builder->school($membership);
        abort_if($school === null || $membership === null, 404);

        return QuestionBank::courseIds($school, $membership->role === Role::Instructor ? $user : null);
    }
}
