<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Learn;

use App\Content\Learning\Learning;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\SearchRequest;
use App\Http\Resources\Learn\KnowledgeHitResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Learn on the phone (A5.3b; candidate screen 8): courses for the person's
 * current state, search inside them, and a lesson to open at a heading,
 * page or second.
 */
final class LearnController extends Controller
{
    /**
     * Courses I can learn.
     *
     * Live courses for my current state, with their lessons.
     *
     * CE and qualifying courses carry `credit` (the approval number and hours)
     * only with an approval in force in my state; otherwise it's null and the
     * course isn't labelled CE or qualifying.
     *
     * @response array{state: string, data: list<array{id: int, title: string, type: 'qualifying'|'ce'|'post_licence'|'test_prep'|'elective', hours: float|null, credit: array{number: string, hours: float|null}|null, unlocked: bool, lessons: list<array{id: int, title: string, minutes: int|null, language: 'en'|'es', locked: bool}>}>}
     */
    public function courses(#[CurrentUser] User $user, Learning $learning): JsonResponse
    {
        return response()->json(['state' => $learning->state($user), 'data' => $learning->courses($user)]);
    }

    /**
     * Search my courses.
     *
     * Finds passages in the lessons of my live courses (keyword and, when
     * switched on, meaning). Each opens at its heading, page or second.
     */
    public function search(SearchRequest $request, #[CurrentUser] User $user, Learning $learning): AnonymousResourceCollection
    {
        return KnowledgeHitResource::collection($learning->search($user, $request->string('q')->toString(), $request->language()));
    }

    /**
     * Open a lesson.
     *
     * Its text by heading, slides and documents page by page (with a signed
     * link to the file), the video (Mux `hls` with a signed token, or the
     * original `file`) and its captions with timings. Links last two hours.
     * 404 unless the lesson is in a live course for my current state; 402
     * (`membership_required`) when it needs a plan I don't have yet (each
     * course's first lesson is free).
     *
     * @response array{data: array{id: int, title: string, language: 'en'|'es', minutes: int|null, course: array{id: int, title: string}, sections: list<array{heading: string|null, text: string}>, documents: list<array{id: int, kind: 'slides'|'document', name: string, url: string, pages: list<array{page: int, text: string}>}>, video: array{type: 'hls'|'file', url: string, duration: int|null, poster: string|null}|null, transcript: list<array{seconds: int, text: string}>, previous: int|null, next: int|null}}
     */
    public function lesson(int $lesson, #[CurrentUser] User $user, Learning $learning): JsonResponse
    {
        $found = $learning->find($user, $lesson);
        abort_if($found === null, 404);

        if (! $learning->canOpen($user, $found)) {
            return response()->json(['message' => __('billing.membershipRequired'), 'membership_required' => true], 402);
        }

        return response()->json(['data' => $learning->lesson($found)]);
    }
}
