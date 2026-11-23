<?php

declare(strict_types=1);

namespace App\Http\Controllers\Builder;

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Content\Approvals\CourseApprovals;
use App\Content\Builder\CourseBuilder;
use App\Content\Builder\LessonReview;
use App\Content\Builder\ModuleSources;
use App\Content\Builder\SourceRole;
use App\Content\CourseStatus;
use App\Content\CourseType;
use App\Content\Delivery;
use App\Content\MediaKind;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\ModuleSource;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The course builder (A7.2; instructor web screen 43, and the school admin's
 * course catalogue): a school's courses, their modules with source material,
 * what AI will make, the settings, and the lessons with their status.
 * Instructors see only the courses they teach; everyone else gets a 404.
 */
final class CourseBuilderController extends Controller
{
    public function index(#[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder): Response
    {
        $membership = $this->membership($user, $roles, $builder);

        return Inertia::render('builder/index', [
            'courses' => $builder->editable($user, $membership)->map(static fn (Course $c): array => [
                'id' => $c->id, 'title' => $c->title, 'type' => $c->type->value, 'delivery' => $c->delivery->value, 'status' => $c->status->value,
                'hours' => $c->hours === null ? null : (float) $c->hours, 'modules' => $c->modules()->count(), 'lessons' => $c->lessons()->count(),
            ])->all(),
            'types' => CourseType::values(),
            'deliveries' => Delivery::values(),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'type' => ['required', Rule::in(CourseType::values())],
            'delivery' => ['required', Rule::in(Delivery::values())],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
        ]);
        $course = $builder->create($user, $this->membership($user, $roles, $builder), [
            'title' => (string) $data['title'], 'type' => (string) $data['type'], 'delivery' => (string) $data['delivery'], 'hours' => is_numeric($data['hours'] ?? null) ? (float) $data['hours'] : null,
        ]);

        return to_route('builder.show', $course);
    }

    public function show(Request $request, Course $course, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, CourseApprovals $approvals): Response
    {
        $this->course($course, $user, $roles, $builder);
        $course->load(['modules.sources', 'modules.lessons', 'approvals']);
        $chosen = $course->modules->firstWhere('id', $request->integer('module')) ?? $course->modules->first();
        $approval = $course->approvals->first(static fn ($a): bool => $a->valid());
        $checkin = $approval === null ? CourseApprovals::CHECKIN_MINUTES : ($approval->checkin_minutes ?? CourseApprovals::CHECKIN_MINUTES);

        return Inertia::render('builder/course', [
            'course' => [
                'id' => $course->id, 'title' => $course->title, 'type' => $course->type->value, 'delivery' => $course->delivery->value,
                'status' => $course->status->value, 'hours' => $course->hours === null ? null : (float) $course->hours,
                'hours_locked' => $builder->hoursLocked($course), 'approval' => $approval?->number,
                'can_publish' => $roles->for($user)?->role === Role::SchoolAdmin,
            ],
            'modules' => $course->modules->map(static fn (CourseModule $m): array => [
                'id' => $m->id, 'title' => $m->title, 'sources' => $m->sources->count(), 'lessons' => $m->lessons->count(),
                'approved' => $m->lessons->filter(static fn (Lesson $l): bool => $l->review_status === LessonReview::Approved)->count(),
                'status' => self::moduleStatus($m),
            ])->all(),
            'module' => $chosen === null ? null : [
                'id' => $chosen->id,
                'title' => $chosen->title,
                'sources' => $chosen->sources->map(static fn (ModuleSource $s): array => [
                    'id' => $s->id, 'role' => $s->role->value, 'name' => $s->original_name, 'size' => $s->size, 'status' => $s->status->value,
                    'failure' => $s->failure === null ? null : __($s->failure), 'pages' => $s->pages, 'words' => $s->words,
                ])->all(),
                'lessons' => $chosen->lessons->map(static fn (Lesson $l): array => [
                    'id' => $l->id, 'title' => $l->title, 'minutes' => $l->minutes, 'language' => $l->language,
                    'status' => $l->review_status === null ? 'approved' : $l->review_status->value, 'has_body' => trim((string) $l->body) !== '', 'body' => $l->body,
                ])->all(),
                'plan' => $builder->plan($course, $chosen, $checkin),
            ],
            'settings' => $builder->settings($course),
            'presenters' => $builder->presenters($course),
            'roles' => array_map(static fn (SourceRole $r): array => ['role' => $r->value, 'types' => array_keys($r->kind()->types()), 'max_mb' => (int) round($r->kind()->maxBytes() / 1024 / 1024)], SourceRole::cases()),
        ]);
    }

    public function settings(Request $request, Course $course, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder): RedirectResponse
    {
        $this->course($course, $user, $roles, $builder);
        $data = $request->validate([
            'language' => ['required', Rule::in(['en', 'es', 'both'])],
            'lesson_minutes' => ['required', 'integer', 'min:3', 'max:30'],
            'presenter' => ['required', 'string', 'max:40'],
        ]);
        $builder->saveSettings($course, ['language' => (string) $data['language'], 'lesson_minutes' => (int) $data['lesson_minutes'], 'presenter' => (string) $data['presenter']], $user);

        return back()->with('success', __('builder.saved'));
    }

    public function seatTime(Request $request, Course $course, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder): RedirectResponse
    {
        $this->course($course, $user, $roles, $builder);
        $data = $request->validate(['hours' => ['required', 'numeric', 'min:0.5', 'max:500'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $builder->askSeatTimeChange($course, (float) $data['hours'], (string) $data['reason'], $user);

        return back()->with('success', __('builder.seatTime.sent'));
    }

    /** School admins publish or hide a course (the A5.3 checks apply: approvals in force, no open legal flags). */
    public function status(Request $request, Course $course, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, Courses $courses): RedirectResponse
    {
        $this->course($course, $user, $roles, $builder);
        abort_unless($roles->for($user)?->role === Role::SchoolAdmin, 403);
        $status = $request->validate(['status' => ['required', Rule::in([CourseStatus::Live->value, CourseStatus::Hidden->value, CourseStatus::Draft->value])]])['status'];
        $courses->setStatus($course->load('approvals'), CourseStatus::from((string) $status), $user);

        return back();
    }

    public function addModule(Request $request, Course $course, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, Courses $courses): RedirectResponse
    {
        $this->course($course, $user, $roles, $builder);
        $title = $request->validate(['title' => ['required', 'string', 'min:2', 'max:160']])['title'];
        $module = $courses->addModule($course, (string) $title, $user);

        return to_route('builder.show', ['course' => $course->id, 'module' => $module->id]);
    }

    public function renameModule(Request $request, CourseModule $module, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, Courses $courses): RedirectResponse
    {
        $this->course($module->course, $user, $roles, $builder);
        $courses->renameModule($module, (string) $request->validate(['title' => ['required', 'string', 'min:2', 'max:160']])['title'], $user);

        return back();
    }

    public function removeModule(CourseModule $module, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, Courses $courses): RedirectResponse
    {
        $this->course($module->course, $user, $roles, $builder);
        $courses->removeModule($module, $user);

        return to_route('builder.show', $module->course_id);
    }

    public function beginSource(Request $request, CourseModule $module, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, ModuleSources $sources): JsonResponse
    {
        $this->course($module->course, $user, $roles, $builder);
        $data = $request->validate(['role' => ['required', Rule::in(SourceRole::values())], 'name' => ['required', 'string', 'max:255'], 'size' => ['required', 'integer', 'min:1']]);
        $started = $sources->begin($module, SourceRole::from((string) $data['role']), (string) $data['name'], (int) $data['size'], $user);

        return response()->json(['id' => $started['source']->id, 'upload' => $started['upload']], 201);
    }

    public function completeSource(ModuleSource $source, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, ModuleSources $sources): JsonResponse
    {
        $this->course($source->module->course, $user, $roles, $builder);
        $done = $sources->complete($source, $user);

        return response()->json(['id' => $done->id, 'status' => $done->status->value]);
    }

    public function removeSource(ModuleSource $source, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, ModuleSources $sources): RedirectResponse
    {
        $this->course($source->module->course, $user, $roles, $builder);
        $sources->remove($source, $user);

        return back();
    }

    public function addLesson(Request $request, CourseModule $module, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder): RedirectResponse
    {
        $this->course($module->course, $user, $roles, $builder);
        $builder->addLesson($module->course, $module, (string) $request->validate(['title' => ['required', 'string', 'min:2', 'max:200']])['title'], $user);

        return back();
    }

    public function updateLesson(Request $request, Lesson $lesson, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, Lessons $lessons): RedirectResponse
    {
        $this->course($lesson->course, $user, $roles, $builder);
        $data = $request->validate(['title' => ['sometimes', 'string', 'min:2', 'max:200'], 'body' => ['sometimes', 'nullable', 'string', 'max:100000'], 'minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:240'], 'language' => ['sometimes', Rule::in(['en', 'es'])]]);
        $lessons->update($lesson, $data, $user);

        // An approved lesson that's edited goes back to draft until approved again.
        if ($lesson->review_status === LessonReview::Approved && array_intersect(array_keys($data), ['body', 'language']) !== []) {
            $lesson->forceFill(['review_status' => LessonReview::Draft])->save();
        }

        return back();
    }

    public function moveLesson(Request $request, Lesson $lesson, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder): RedirectResponse
    {
        $this->course($lesson->course, $user, $roles, $builder);
        $builder->move($lesson, $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'] === 'up' ? -1 : 1, $user);

        return back();
    }

    public function approveLesson(Lesson $lesson, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder): RedirectResponse
    {
        $this->course($lesson->course, $user, $roles, $builder);
        $builder->approve($lesson, $user);

        return back()->with('success', __('builder.lessonApproved'));
    }

    public function removeLesson(Lesson $lesson, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, Lessons $lessons): RedirectResponse
    {
        $this->course($lesson->course, $user, $roles, $builder);
        $lessons->delete($lesson, $user);

        return back();
    }

    public function restoreLesson(int $lesson, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, Lessons $lessons): RedirectResponse
    {
        $found = Lesson::query()->withTrashed()->findOrFail($lesson);
        $this->course($found->course, $user, $roles, $builder);
        $lessons->restore($found, $user);

        return back();
    }

    /** No sources, reading, ready to generate, or the lessons' progress. */
    private static function moduleStatus(CourseModule $module): string
    {
        return match (true) {
            $module->lessons->isNotEmpty() && $module->lessons->every(static fn (Lesson $l): bool => $l->review_status === LessonReview::Approved) => 'approved',
            $module->lessons->contains(static fn (Lesson $l): bool => $l->review_status === LessonReview::InReview) => 'in_review',
            $module->lessons->contains(static fn (Lesson $l): bool => $l->review_status === LessonReview::Generating) => 'generating',
            $module->lessons->isNotEmpty() => 'draft',
            $module->sources->contains(static fn (ModuleSource $s): bool => $s->kind !== MediaKind::Video && $s->status->value === 'processing') => 'reading',
            $module->sources->isNotEmpty() => 'sources',
            default => 'empty',
        };
    }

    private function membership(User $user, ActiveRole $roles, CourseBuilder $builder): Membership
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && $builder->school($membership) !== null, 404);

        return $membership;
    }

    private function course(Course $course, User $user, ActiveRole $roles, CourseBuilder $builder): void
    {
        abort_unless($builder->canEdit($user, $this->membership($user, $roles, $builder), $course), 404);
    }
}
