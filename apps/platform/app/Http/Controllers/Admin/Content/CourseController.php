<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Content\CourseType;
use App\Content\Review\ContentChecks;
use App\Enums\StateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\CourseRequest;
use App\Http\Requests\Admin\Content\CourseStatusRequest;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\KbPassage;
use App\Models\Lesson;
use App\Models\State;
use App\Models\User;
use App\Services\Content\Courses;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One course (A5.3): details, states and approval numbers, status, modules
 * and lessons. CE and qualifying courses go live only with an approval
 * number in every state they're tagged to.
 */
final class CourseController extends Controller
{
    public function store(CourseRequest $request, #[CurrentUser] User $actor, Courses $courses): RedirectResponse
    {
        /** @var array{title: string, type: string, description?: string|null, states?: list<string>|null, hours?: float|int|string|null} $data */
        $data = $request->validated();
        $course = $courses->create($data, $actor);

        return to_route('content.courses.show', $course);
    }

    public function show(Course $course, ContentChecks $checks): Response
    {
        $lessons = Lesson::query()->where('course_id', $course->id)->withCount(['media', 'passages'])->orderBy('position')->orderBy('id')->get();
        $embedded = KbPassage::query()->where('course_id', $course->id)->whereNotNull('embedded_at')->count();

        return Inertia::render('admin/content/course', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'type' => $course->type->value,
                'description' => $course->description,
                'states' => $course->states,
                'hours' => $course->hours === null ? null : (float) $course->hours,
                'approvals' => $course->approvalNumbers() ?: (object) [],
                'status' => $course->status->value,
                'status_note' => $course->status_note,
                'needs_approval' => $course->type->needsApproval(),
                'missing_approvals' => $course->missingApprovals(),
                'published_at' => $course->published_at?->toIso8601String(),
                'passages' => KbPassage::query()->where('course_id', $course->id)->count(),
                'embedded' => $embedded,
            ],
            'modules' => $course->modules->map(static fn (CourseModule $m): array => ['id' => $m->id, 'title' => $m->title])->all(),
            'lessons' => $lessons->map(static fn (Lesson $l): array => [
                'id' => $l->id,
                'title' => $l->title,
                'module_id' => $l->course_module_id,
                'language' => $l->language,
                'minutes' => $l->minutes,
                'ingest_status' => $l->ingest_status->value,
                'files' => $l->media_count ?? 0,
                'passages' => $l->passages_count ?? 0,
            ])->all(),
            'checks' => $checks->for($course),
            'types' => CourseType::values(),
            'states' => self::stateOptions(),
        ]);
    }

    public function update(CourseRequest $request, Course $course, #[CurrentUser] User $actor, Courses $courses): RedirectResponse
    {
        $courses->update($course, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('content.courses.saved')]);

        return back();
    }

    public function status(CourseStatusRequest $request, Course $course, #[CurrentUser] User $actor, Courses $courses): RedirectResponse
    {
        $note = $request->input('note');
        $courses->setStatus($course, $request->status(), $actor, is_string($note) ? $note : null);

        return back();
    }

    /**
     * States content can be tagged to, with whether each is live or being
     * prepared (shown first; the rest behind "Show every state").
     *
     * @return list<array{code: string, name: string, active: bool}>
     */
    public static function stateOptions(): array
    {
        return array_values(State::query()
            ->orderBy('name')
            ->get(['code', 'name', 'status'])
            ->map(static fn (State $s): array => [
                'code' => $s->code,
                'name' => $s->name,
                'active' => in_array($s->status, [StateStatus::Live, StateStatus::InPreparation], true),
            ])
            ->all());
    }

    /** Deleted at once; the toast offers Undo. */
    public function destroy(Course $course, #[CurrentUser] User $actor, Courses $courses): RedirectResponse
    {
        $courses->delete($course, $actor);

        return to_route('content.library');
    }

    public function restore(int $course, #[CurrentUser] User $actor, Courses $courses): RedirectResponse
    {
        $courses->restore(Course::onlyTrashed()->findOrFail($course), $actor);

        return back();
    }
}
