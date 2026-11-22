<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Content\Knowledge\Embedders;
use App\Content\MediaKind;
use App\Content\Video\SimulatedVideoHost;
use App\Content\Video\VideoHosts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\LessonRequest;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\KbPassage;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\User;
use App\Services\Content\Lessons;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One lesson (A5.3): its text, files (uploaded straight to storage, then
 * scanned and processed) and its knowledge base passages, each with where
 * it opens.
 */
final class LessonController extends Controller
{
    public function store(LessonRequest $request, Course $course, #[CurrentUser] User $actor, Lessons $lessons): RedirectResponse
    {
        /** @var array{title: string, course_module_id?: int|null, language?: string|null} $data */
        $data = $request->validated();
        $lesson = $lessons->create($course, $data, $actor);

        return to_route('content.lessons.show', $lesson);
    }

    public function show(Lesson $lesson, Embedders $embedders, VideoHosts $videos): Response
    {
        $course = $lesson->course;
        $passages = KbPassage::query()->where('lesson_id', $lesson->id)->orderBy('position')->get(['id', 'kind', 'heading', 'page', 'seconds', 'content', 'embedded_at']);
        $host = $videos->host();

        return Inertia::render('admin/content/lesson', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'language' => $lesson->language,
                'minutes' => $lesson->minutes,
                'body' => $lesson->body ?? '',
                'module_id' => $lesson->course_module_id,
                'ingest_status' => $lesson->ingest_status->value,
                'ingested_at' => $lesson->ingested_at?->toIso8601String(),
            ],
            'course' => ['id' => $course->id, 'title' => $course->title],
            'modules' => CourseModule::query()->where('course_id', $course->id)->orderBy('position')->get(['id', 'title'])
                ->map(static fn (CourseModule $m): array => ['id' => $m->id, 'title' => $m->title])->all(),
            'files' => $lesson->media->map(static fn (LessonMedia $m): array => [
                'id' => $m->id,
                'kind' => $m->kind->value,
                'name' => $m->original_name,
                'size' => $m->size,
                'status' => $m->status->value,
                'failure' => $m->failure,
                'pages' => $m->pages,
                'duration_seconds' => $m->duration_seconds,
                'captions' => $m->transcript !== null,
            ])->all(),
            'kinds' => array_map(static fn (MediaKind $kind): array => [
                'kind' => $kind->value,
                'extensions' => array_keys($kind->types()),
                'max_mb' => (int) round($kind->maxBytes() / 1024 / 1024),
            ], MediaKind::cases()),
            'knowledge' => [
                'passages' => $passages->count(),
                'embedded' => $passages->whereNotNull('embedded_at')->count(),
                'meaning' => $embedders->embedder() !== null,
                'video_host' => match (true) {
                    $host === null => 'off',
                    $host instanceof SimulatedVideoHost => 'simulator',
                    default => 'mux',
                },
                'sample' => $passages->take(30)->map(static fn (KbPassage $p): array => [
                    'id' => $p->id,
                    'kind' => $p->kind,
                    'heading' => $p->heading,
                    'page' => $p->page,
                    'seconds' => $p->seconds,
                    'excerpt' => Str::limit($p->content, 220),
                ])->values()->all(),
            ],
        ]);
    }

    public function update(LessonRequest $request, Lesson $lesson, #[CurrentUser] User $actor, Lessons $lessons): RedirectResponse
    {
        $lessons->update($lesson, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('content.lessons.saved')]);

        return back();
    }

    /** Deleted at once; the toast offers Undo. */
    public function destroy(Lesson $lesson, #[CurrentUser] User $actor, Lessons $lessons): RedirectResponse
    {
        $lessons->delete($lesson, $actor);

        return to_route('content.courses.show', $lesson->course_id);
    }

    public function restore(int $lesson, #[CurrentUser] User $actor, Lessons $lessons): RedirectResponse
    {
        $lessons->restore(Lesson::onlyTrashed()->findOrFail($lesson), $actor);

        return back();
    }

    public function reindex(Lesson $lesson, #[CurrentUser] User $actor, Lessons $lessons): RedirectResponse
    {
        $lessons->reindex($lesson, $actor);

        return back();
    }
}
