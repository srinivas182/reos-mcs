<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Content\CourseStatus;
use App\Content\Review\ContentChecks;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\State;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Content and approvals (web journey screen 63, A5.3b): what's waiting for
 * review, with its tenant, states, checks and status, and the rules every
 * piece of content meets before it's live. Opens the content library.
 */
final class ContentApprovalsController extends Controller
{
    public function index(Request $request, ContentChecks $checks): Response
    {
        $queueOnly = $request->boolean('queue');
        $names = State::query()->pluck('name', 'code')->all();

        $courses = Course::query()
            ->withCount('lessons')
            ->when($queueOnly, static fn ($q) => $q->where('status', CourseStatus::InReview))
            ->orderByRaw("CASE status WHEN 'in_review' THEN 0 WHEN 'draft' THEN 1 WHEN 'live' THEN 2 ELSE 3 END")
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return Inertia::render('admin/content/index', [
            'queueOnly' => $queueOnly,
            'waiting' => Course::query()->where('status', CourseStatus::InReview)->count(),
            'items' => $courses->map(static function (Course $course) use ($checks, $names): array {
                $result = $checks->for($course);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'lessons' => $course->lessons_count ?? 0,
                    // School content (Sprint 7) names its school here.
                    'tenant' => null,
                    'states' => $course->states === null ? null : array_map(static fn (string $code): string => is_string($names[$code] ?? null) ? $names[$code] : $code, $course->states),
                    'status' => $course->status->value,
                    'missing_approvals' => $result['approvals'],
                    'flags' => $result['open'],
                ];
            })->values()->all(),
        ]);
    }
}
