<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Social;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Social\SocialCalendar;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The content calendar (B12.3, ADR 0052): a week or a month of posts; move and reuse. */
final class ContentCalendarController extends Controller
{
    /**
     * The calendar.
     *
     * A week (Sunday to Saturday) or a month of my posts in my time zone: scheduled posts at their time, posts on the day they went out, and drafts planned for a day.
     *
     * @response array{data: array{view: 'week'|'month', date: string, from: string, to: string, previous: string, next: string, timezone: string, days: list<array{date: string, today: bool, posts: list<array{id: string, status: string, body: string, time: string|null, channels: list<string>, thumb: string|null, kind: string|null, movable: bool}>}>, scheduled: int}}
     */
    public function index(Request $request, #[CurrentUser] User $user, SocialCalendar $calendar): JsonResponse
    {
        $request->validate(['view' => ['nullable', 'in:week,month'], 'date' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['data' => $calendar->view($user, $request->string('view', 'week')->toString(), $request->query('date') === null ? null : $request->string('date')->toString())]);
    }

    /**
     * Move a post to another day.
     *
     * A scheduled post keeps its time of day; a draft is planned for the day. Posts that went out can't move (reuse them instead).
     *
     * @response array{data: array{id: string, status: string, body: string, time: string|null, channels: list<string>, thumb: string|null, kind: string|null, movable: bool}}
     */
    public function move(Request $request, string $post, #[CurrentUser] User $user, SocialCalendar $calendar): JsonResponse
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['data' => $calendar->move($user, $post, $request->string('date')->toString())]);
    }

    /**
     * Reuse a post.
     *
     * A new draft with the same words, media and places, planned for the day.
     *
     * @status 201
     *
     * @response array{data: array{id: string, status: string, body: string, time: string|null, channels: list<string>, thumb: string|null, kind: string|null, movable: bool}}
     */
    public function reuse(Request $request, string $post, #[CurrentUser] User $user, SocialCalendar $calendar): JsonResponse
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['data' => $calendar->reuse($user, $post, $request->string('date')->toString())], 201);
    }
}
