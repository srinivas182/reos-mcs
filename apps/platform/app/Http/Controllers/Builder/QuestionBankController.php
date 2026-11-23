<?php

declare(strict_types=1);

namespace App\Http\Controllers\Builder;

use App\Authorization\Role;
use App\Content\Builder\CourseBuilder;
use App\Content\Review\QuestionBank;
use App\Enums\QuizTopic;
use App\Http\Controllers\Controller;
use App\Models\QuestionDispute;
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
 * courses they teach.
 */
final class QuestionBankController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, QuestionBank $bank): Response
    {
        $courses = $this->courses($user, $roles, $builder);
        $asked = $request->query('tab');
        $tab = is_string($asked) && in_array($asked, ['to_review', 'approved', 'rejected', 'disputed'], true) ? $asked : 'to_review';
        $rows = $bank->tab($courses, $tab);

        return Inertia::render('builder/questions', [
            'tab' => $tab,
            'counts' => $bank->counts($courses),
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

    public function update(Request $request, ReviewItem $item, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, QuestionBank $bank): RedirectResponse
    {
        $this->owns($item, $user, $roles, $builder);
        $data = $request->validate([
            'en.prompt' => ['required', 'string', 'min:5', 'max:400'], 'en.options' => ['required', 'array', 'min:2', 'max:5'], 'en.options.*' => ['required', 'string', 'max:200'], 'en.explanation' => ['nullable', 'string', 'max:400'],
            'es.prompt' => ['required', 'string', 'min:5', 'max:400'], 'es.options' => ['required', 'array', 'min:2', 'max:5'], 'es.options.*' => ['required', 'string', 'max:200'], 'es.explanation' => ['nullable', 'string', 'max:400'],
            'correct' => ['required', 'integer', 'min:0', 'max:4'],
            'topic' => ['required', Rule::enum(QuizTopic::class)],
            'difficulty' => ['required', Rule::in(QuestionBank::DIFFICULTIES)],
            'portion' => ['required', Rule::in(QuestionBank::PORTIONS)],
        ]);
        $bank->edit($item, self::edit($data), $user);

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
     * @param  array<string, mixed>  $data
     * @return array{en: array{prompt: string, options: list<string>, explanation: string}, es: array{prompt: string, options: list<string>, explanation: string}, correct: int, topic: string, difficulty: string, portion: string}
     */
    private static function edit(array $data): array
    {
        $language = static function (mixed $v): array {
            $v = is_array($v) ? $v : [];

            return ['prompt' => is_string($v['prompt'] ?? null) ? $v['prompt'] : '', 'options' => array_values(array_filter((array) ($v['options'] ?? []), is_string(...))), 'explanation' => is_string($v['explanation'] ?? null) ? $v['explanation'] : ''];
        };

        return [
            'en' => $language($data['en'] ?? null), 'es' => $language($data['es'] ?? null),
            'correct' => is_numeric($data['correct'] ?? null) ? (int) $data['correct'] : 0,
            'topic' => is_string($data['topic'] ?? null) ? $data['topic'] : '', 'difficulty' => is_string($data['difficulty'] ?? null) ? $data['difficulty'] : '', 'portion' => is_string($data['portion'] ?? null) ? $data['portion'] : '',
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
