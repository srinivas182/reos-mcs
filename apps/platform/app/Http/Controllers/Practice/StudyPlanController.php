<?php

declare(strict_types=1);

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Models\StudyTask;
use App\Models\User;
use App\Practice\CandidateToday;
use App\Practice\PracticeAccess;
use App\Practice\StudyPlans;
use App\Services\Calendar\CalendarEvents;
use App\Services\Onboarding\Onboarding;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The study plan's week (candidate web screen 6, A8.3): tasks per day by
 * type, the weekly goal, the catch-up rule and the reminder; move a task to
 * another day, change the exam month or minutes a day. The calendar it's
 * placed on is the one chosen in Connections (B4.1).
 */
final class StudyPlanController extends Controller
{
    public function show(Request $request, #[CurrentUser] User $user, PracticeAccess $access, CandidateToday $today, StudyPlans $plans, CalendarEvents $calendar): Response
    {
        $access->allow($user);
        $week = $request->validate(['week' => ['nullable', 'date_format:Y-m-d']])['week'] ?? null;
        $from = (is_string($week) ? CarbonImmutable::parse($week, StudyPlans::TIMEZONE) : StudyPlans::today())->startOfWeek(CarbonImmutable::MONDAY);
        $summary = $today->for($user);
        $target = $calendar->target($user);

        return Inertia::render('study-plan', [
            'week' => ['from' => $from->toDateString(), 'previous' => $from->subWeek()->toDateString(), 'next' => $from->addWeek()->toDateString(), 'today' => StudyPlans::today()->toDateString()],
            'days' => $today->week($user, $from),
            'summary' => $summary,
            'calendar' => $target === null ? null : ['tool' => $target[0]->integration_id, 'calendar' => $target[1]],
        ]);
    }

    /** Drag a task to another day (or choose the day from its menu). */
    public function move(Request $request, int $task, #[CurrentUser] User $user, PracticeAccess $access, StudyPlans $plans): RedirectResponse
    {
        $access->allow($user);
        $found = StudyTask::query()->where('user_id', $user->id)->whereHas('plan', fn ($q) => $q->where('active', true))->findOrFail($task);
        $day = (string) $request->validate(['day' => ['required', 'date_format:Y-m-d']])['day'];
        $plans->move($found, CarbonImmutable::parse($day, StudyPlans::TIMEZONE));

        return back()->with('success', __('practice.plan.moved'));
    }

    /** Change the exam month, minutes a day or reminder time: the plan is rebuilt. */
    public function settings(Request $request, #[CurrentUser] User $user, PracticeAccess $access, Onboarding $onboarding): RedirectResponse
    {
        $access->allow($user);
        $data = $request->validate([
            'exam_month' => ['required', 'date_format:Y-m', 'after_or_equal:'.now()->format('Y-m')],
            'minutes_per_day' => ['required', 'integer', 'in:10,20,30,45,60'],
            'reminder_time' => ['nullable', 'date_format:H:i'],
        ]);
        $onboarding->save($user, [
            'exam_month' => Carbon::createFromFormat('Y-m', (string) $data['exam_month'])?->startOfMonth(),
            'minutes_per_day' => (int) $data['minutes_per_day'],
            'reminder_time' => is_string($data['reminder_time'] ?? null) ? $data['reminder_time'] : null,
        ]);

        return back()->with('success', __('practice.plan.rebuilt'));
    }
}
