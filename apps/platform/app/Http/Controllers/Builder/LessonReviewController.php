<?php

declare(strict_types=1);

namespace App\Http\Controllers\Builder;

use App\Authorization\Role;
use App\Content\Builder\CourseBuilder;
use App\Content\MediaKind;
use App\Content\Review\LegalRule;
use App\Content\Review\LessonReviews;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\LessonVersion;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Content\LessonFiles;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI lesson review (instructor web screen 44, A8.1, ADR 0031): the video in
 * either language, the check-ins and flashcards, and the script line by line
 * with its source, any legal flag with its rule and a suggestion; Approve,
 * Edit, Use suggestion, Re-record, Comment per line; Approve and publish or
 * Send back for the lesson. Only the school's admins and the course's
 * instructors may review (404 otherwise).
 */
final class LessonReviewController extends Controller
{
    /** AI lessons (instructor web nav): every AI lesson on the courses the person builds, waiting ones first. */
    public function index(#[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, LessonReviews $reviews): Response
    {
        $membership = $roles->for($user);
        abort_if($membership === null || $builder->school($membership) === null, 404);
        $courses = $builder->editable($user, $membership)->pluck('id');
        $lessons = Lesson::query()->whereIn('course_id', $courses)->whereHas('versions')->with(['course', 'versions'])->get();
        $order = ['in_review' => 0, 'generating' => 1, 'draft' => 2, 'approved' => 3];

        return Inertia::render('builder/ai-lessons', [
            'lessons' => $lessons->map(static function (Lesson $l) use ($reviews): array {
                $version = $l->versions->sortByDesc('version')->first();

                return [
                    'id' => $l->id, 'title' => $l->title, 'course' => $l->course->title, 'status' => $l->review_status === null ? 'approved' : $l->review_status->value,
                    'version' => $version?->version, 'flags' => $version === null ? 0 : count($version->flags ?? []),
                    'blockers' => $version === null ? 0 : count($reviews->blockers($version)),
                    'updated_at' => $version?->updated_at?->toIso8601String(),
                ];
            })->sortBy(fn (array $l): string => ($order[$l['status']] ?? 9).'-'.$l['title'])->values()->all(),
        ]);
    }

    public function show(Lesson $lesson, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, LessonReviews $reviews): Response
    {
        $this->allow($lesson, $user, $roles, $builder);
        $version = $reviews->latest($lesson);
        abort_if($version === null, 404);
        $version->load('renders');
        $videos = $lesson->media()->where('kind', MediaKind::Video->value)->whereNotNull('language')->where('status', 'ready')->get()->keyBy('id');
        $flags = collect($version->flags ?? []);

        return Inertia::render('builder/review', [
            'lesson' => ['id' => $lesson->id, 'title' => $lesson->title, 'course' => ['id' => $lesson->course_id, 'title' => $lesson->course->title]],
            'version' => [
                'id' => $version->id, 'version' => $version->version, 'status' => $version->status, 'review_state' => $version->review_state, 'review_note' => $version->review_note,
                'vendor' => $version->vendor, 'credits' => $version->credits,
            ],
            'versions' => $lesson->versions()->reorder('version', 'desc')->get(['version', 'status', 'review_state', 'created_at'])
                ->map(static fn (LessonVersion $v): array => ['version' => $v->version, 'status' => $v->status, 'review_state' => $v->review_state, 'created_at' => $v->created_at?->toIso8601String()])->all(),
            'lines' => array_map(static function (array $line) use ($version, $flags): array {
                $review = $version->review($line['n']);

                return [
                    ...$line,
                    'status' => $review['status'],
                    'rerecord' => $review['rerecord'],
                    'suggestion' => $review['suggestion'],
                    'comments' => $review['comments'],
                    'flags' => $flags->where('line', $line['n'])->map(static fn (array $f): array => [
                        'rule' => $f['rule'], 'language' => $f['language'], 'source' => LegalRule::tryFrom($f['rule'])?->source(),
                    ])->values()->all(),
                ];
            }, $version->lines ?? []),
            'videos' => $version->renders->map(static function ($r) use ($videos): array {
                $media = $r->lesson_media_id === null ? null : $videos->get($r->lesson_media_id);

                return ['language' => $r->language, 'status' => $r->status, 'simulated' => $r->simulated, 'url' => $media instanceof LessonMedia ? LessonFiles::playableUrl($media, 60) : null];
            })->values()->all(),
            'items' => ReviewItem::query()->where('lesson_version_id', $version->id)->whereIn('kind', ['checkin', 'flashcard'])->orderBy('kind')->orderBy('id')->get()
                ->map(static fn (ReviewItem $i): array => ['id' => $i->id, 'kind' => $i->kind, 'after_line' => $i->after_line, 'status' => $i->status, 'content' => $i->content])->all(),
            'blockers' => array_map(static fn (array $b): array => ['text' => trans_choice($b['key'], $b['count'], ['count' => $b['count']])], $reviews->blockers($version)),
            'pending_rerecord' => count($reviews->pendingRerecord($version)),
        ]);
    }

    public function line(Request $request, Lesson $lesson, int $line, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, LessonReviews $reviews): RedirectResponse
    {
        $version = $this->version($lesson, $user, $roles, $builder, $reviews);
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'edit', 'use_suggestion', 'rerecord'])],
            'en' => ['required_if:action,edit', 'nullable', 'string', 'min:2', 'max:600'],
            'es' => ['required_if:action,edit', 'nullable', 'string', 'min:2', 'max:600'],
        ]);

        match ($data['action']) {
            'approve' => $reviews->approveLine($version, $line, $user),
            'edit' => $reviews->editLine($version, $line, (string) $data['en'], (string) $data['es'], $user),
            'use_suggestion' => $reviews->useSuggestion($version, $line, $user),
            default => $reviews->rerecord($version, $line, $user),
        };

        return back();
    }

    /** A compliant rewrite of a flagged line, shown on the line. */
    public function suggest(Lesson $lesson, int $line, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, LessonReviews $reviews): RedirectResponse
    {
        $reviews->suggest($this->version($lesson, $user, $roles, $builder, $reviews), $line, $user);

        return back();
    }

    public function comment(Request $request, Lesson $lesson, int $line, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, LessonReviews $reviews): RedirectResponse
    {
        $version = $this->version($lesson, $user, $roles, $builder, $reviews);
        $reviews->comment($version, $line, (string) $request->validate(['body' => ['required', 'string', 'min:1', 'max:2000']])['body'], $user);

        return back();
    }

    public function item(Request $request, Lesson $lesson, ReviewItem $item, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, LessonReviews $reviews): RedirectResponse
    {
        $version = $this->version($lesson, $user, $roles, $builder, $reviews);
        abort_unless($item->lesson_version_id === $version->id, 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        $reviews->item($item, (string) $data['decision'], $user);

        return back();
    }

    public function render(Lesson $lesson, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, LessonReviews $reviews): RedirectResponse
    {
        $reviews->renderAgain($this->version($lesson, $user, $roles, $builder, $reviews), $user);

        return to_route('builder.show', $lesson->course_id)->with('success', __('review.rendering'));
    }

    public function publish(Lesson $lesson, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, LessonReviews $reviews): RedirectResponse
    {
        $reviews->publish($this->version($lesson, $user, $roles, $builder, $reviews), $user);

        return to_route('builder.show', $lesson->course_id)->with('success', __('review.published'));
    }

    public function sendBack(Request $request, Lesson $lesson, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, LessonReviews $reviews): RedirectResponse
    {
        $version = $this->version($lesson, $user, $roles, $builder, $reviews);
        $reviews->sendBack($version, (string) $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']])['note'], $user);

        return to_route('builder.show', $lesson->course_id)->with('success', __('review.sentBack'));
    }

    private function version(Lesson $lesson, User $user, ActiveRole $roles, CourseBuilder $builder, LessonReviews $reviews): LessonVersion
    {
        $this->allow($lesson, $user, $roles, $builder);
        $version = $reviews->latest($lesson);
        abort_if($version === null, 404);

        return $version;
    }

    private function allow(Lesson $lesson, User $user, ActiveRole $roles, CourseBuilder $builder): void
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && in_array($membership->role, [Role::SchoolAdmin, Role::Instructor], true) && $builder->canEdit($user, $membership, $lesson->course), 404);
    }
}
