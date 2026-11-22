<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Content\CourseStatus;
use App\Content\CourseType;
use App\Content\Knowledge\KnowledgeSearch;
use App\Content\Knowledge\SearchHit;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Content library (A5.3; opened from web journey screen 63, Content and
 * approvals): every REOS course with its type, states, lessons and status,
 * New course, and a search box that tries the knowledge base as a learner
 * in a state would see it (or across drafts too).
 */
final class ContentLibraryController extends Controller
{
    public function index(Request $request, KnowledgeSearch $search): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:300'],
            'state' => ['nullable', 'string', 'size:2', Rule::exists('states', 'code')],
            'language' => ['nullable', Rule::in(Lesson::LANGUAGES)],
            'drafts' => ['nullable', 'boolean'],
        ]);

        $q = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        $state = is_string($filters['state'] ?? null) ? strtoupper($filters['state']) : null;
        $language = is_string($filters['language'] ?? null) ? $filters['language'] : null;
        $drafts = (bool) ($filters['drafts'] ?? false);

        $courses = Course::query()->withCount('lessons')->orderByRaw("CASE status WHEN 'live' THEN 0 WHEN 'in_review' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")->orderBy('title')->get();

        return Inertia::render('admin/content/library', [
            'courses' => $courses->map(static fn (Course $course): array => [
                'id' => $course->id,
                'title' => $course->title,
                'type' => $course->type->value,
                'states' => $course->states,
                'hours' => $course->hours === null ? null : (float) $course->hours,
                'status' => $course->status->value,
                'lessons' => $course->lessons_count ?? 0,
                'missing_approvals' => $course->missingApprovals(),
                'updated_at' => $course->updated_at?->toIso8601String(),
            ])->all(),
            'types' => CourseType::values(),
            'statuses' => CourseStatus::values(),
            'states' => CourseController::stateOptions(),
            'search' => [
                'q' => $q,
                'state' => $state,
                'language' => $language,
                'drafts' => $drafts,
                'results' => $q === '' ? null : array_map(
                    static fn (SearchHit $hit): array => $hit->toArray(),
                    $search->search($q, $state === null ? null : [$state], $language, 10, liveOnly: ! $drafts),
                ),
            ],
        ]);
    }
}
