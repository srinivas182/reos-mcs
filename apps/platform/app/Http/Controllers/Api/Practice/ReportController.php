<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Practice;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Practice\PracticeAccess;
use App\Practice\WeeklyReports;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The weekly progress report (candidate screen 55, A9.2e). */
final class ReportController extends Controller
{
    /**
     * My week.
     *
     * Time studied with minutes per day against the plan, questions
     * answered, flashcards reviewed, the readiness change, lessons finished
     * and course hours, the weakest topic and the streak, for the week
     * holding `week` (this week by default), and how it's shared.
     *
     * @response array{data: array{report: array{week_start: string, week_end: string, first_name: string, minutes: int, goal_minutes: int, per_day: list<int>, questions: int, cards: int, readiness: array{now: int|null, before: int|null, change: int|null}, lessons: int, hours: float, weakest: string|null, streak: int}, shares: array{school: bool, can_share_school: bool, link: string|null}}}
     */
    public function show(Request $request, #[CurrentUser] User $user, PracticeAccess $access, WeeklyReports $reports): JsonResponse
    {
        $access->allow($user);
        $request->validate(['week' => ['nullable', 'date_format:Y-m-d']]);
        $monday = self::monday($request);

        return response()->json(['data' => ['report' => $reports->for($user, $monday), 'shares' => $reports->shares($user, $monday)]]);
    }

    /**
     * Share my week.
     *
     * With my school (they see it only once shared), or as a link for a
     * study buddy (first name only, for 14 days).
     *
     * @response array{data: array{school: bool, can_share_school: bool, link: string|null}}
     */
    public function share(Request $request, #[CurrentUser] User $user, PracticeAccess $access, WeeklyReports $reports): JsonResponse
    {
        $access->allow($user);
        $with = $request->validate(['with' => ['required', Rule::in(['school', 'link'])], 'week' => ['nullable', 'date_format:Y-m-d']])['with'];
        $monday = self::monday($request);
        $reports->share($user, $monday, is_string($with) ? $with : 'link');

        return response()->json(['data' => $reports->shares($user, $monday)]);
    }

    private static function monday(Request $request): CarbonImmutable
    {
        $week = $request->input('week', $request->query('week'));

        return WeeklyReports::weekOf(is_string($week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) === 1 ? CarbonImmutable::parse($week) : null);
    }
}
