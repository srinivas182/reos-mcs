<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Licence;

use App\Http\Controllers\Controller;
use App\Licences\Journey\LicenceJourneys;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The licence journey (A10.3a, ADR 0041; candidate screens 16, 15, 14 and
 * 19): the state's steps with check-offs, the application window, ready to
 * book and the exam date, the exam-day kit, and the result per portion with
 * the retake plan.
 */
final class LicenceJourneyController extends Controller
{
    /**
     * My licence journey.
     *
     * Steps from the state's rules, the one-year window from the application, readiness to book, the booked exam, the exam-day kit, results and the retake plan.
     *
     * @response array{data: array{state: array{code: string, name: string}, steps: list<array{code: string, title: string, detail: string, url: string|null, done_on: string|null, auto: bool}>, window: array{filed_on: string|null, ends_on: string|null, days_left: int|null, months: int}, readiness: array{percent: int|null, national: int|null, state: int|null, line: int, ready: bool, simulations: int, needed: int}, exam: array{vendor: string|null, book_url: string|null, on: string|null, time: string|null, centre: string|null, days_to: int|null, directions_url: string|null}, kit: array{items: list<array{key: 'id'|'confirmation'|'final_simulation'|'flashcards'|'sleep', done: bool}>, rules: string|null}, results: array{national: array{passed: bool|null, attempts: int, failed: int, on: string|null}, state: array{passed: bool|null, attempts: int, failed: int, on: string|null}, can_enter: bool, attempts_allowed: int, passed_both: bool}, retake: array{portions: list<'national'|'state'>, topics: list<string>, more_education: bool, attempts_left: array{national?: int, state?: int}}|null}}
     */
    public function show(#[CurrentUser] User $user, LicenceJourneys $journeys): JsonResponse
    {
        return response()->json(['data' => $journeys->show($user)]);
    }

    /**
     * Check a step off, or un-check it.
     *
     * With the date it was done (today if left out). Checking off the application starts the window.
     *
     * @response array{data: array{state: array{code: string, name: string}, steps: list<array{code: string, title: string, detail: string, url: string|null, done_on: string|null, auto: bool}>, window: array{filed_on: string|null, ends_on: string|null, days_left: int|null, months: int}, readiness: array{percent: int|null, national: int|null, state: int|null, line: int, ready: bool, simulations: int, needed: int}, exam: array{vendor: string|null, book_url: string|null, on: string|null, time: string|null, centre: string|null, days_to: int|null, directions_url: string|null}, kit: array{items: list<array{key: 'id'|'confirmation'|'final_simulation'|'flashcards'|'sleep', done: bool}>, rules: string|null}, results: array{national: array{passed: bool|null, attempts: int, failed: int, on: string|null}, state: array{passed: bool|null, attempts: int, failed: int, on: string|null}, can_enter: bool, attempts_allowed: int, passed_both: bool}, retake: array{portions: list<'national'|'state'>, topics: list<string>, more_education: bool, attempts_left: array{national?: int, state?: int}}|null}}
     */
    public function step(Request $request, string $code, #[CurrentUser] User $user, LicenceJourneys $journeys): JsonResponse
    {
        $request->validate(['done' => ['required', 'boolean'], 'on' => ['nullable', 'date_format:Y-m-d']]);
        $journeys->check($user, $code, $request->boolean('done'), $request->filled('on') ? CarbonImmutable::parse($request->string('on')->toString()) : null);

        return response()->json(['data' => $journeys->show($user)]);
    }

    /**
     * Set my exam date.
     *
     * From the testing vendor's confirmation; the study plan and reminders follow it. Null clears it.
     *
     * @response array{data: array{state: array{code: string, name: string}, steps: list<array{code: string, title: string, detail: string, url: string|null, done_on: string|null, auto: bool}>, window: array{filed_on: string|null, ends_on: string|null, days_left: int|null, months: int}, readiness: array{percent: int|null, national: int|null, state: int|null, line: int, ready: bool, simulations: int, needed: int}, exam: array{vendor: string|null, book_url: string|null, on: string|null, time: string|null, centre: string|null, days_to: int|null, directions_url: string|null}, kit: array{items: list<array{key: 'id'|'confirmation'|'final_simulation'|'flashcards'|'sleep', done: bool}>, rules: string|null}, results: array{national: array{passed: bool|null, attempts: int, failed: int, on: string|null}, state: array{passed: bool|null, attempts: int, failed: int, on: string|null}, can_enter: bool, attempts_allowed: int, passed_both: bool}, retake: array{portions: list<'national'|'state'>, topics: list<string>, more_education: bool, attempts_left: array{national?: int, state?: int}}|null}}
     */
    public function exam(Request $request, #[CurrentUser] User $user, LicenceJourneys $journeys): JsonResponse
    {
        $request->validate([
            'exam_on' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'centre' => ['nullable', 'string', 'max:160'],
        ]);
        $journeys->book(
            $user,
            $request->filled('exam_on') ? CarbonImmutable::parse($request->string('exam_on')->toString()) : null,
            $request->filled('time') ? $request->string('time')->toString() : null,
            $request->filled('centre') ? $request->string('centre')->trim()->toString() : null,
        );

        return response()->json(['data' => $journeys->show($user)]);
    }

    /**
     * Tick an exam-day checklist item.
     *
     * @response array{data: array{state: array{code: string, name: string}, steps: list<array{code: string, title: string, detail: string, url: string|null, done_on: string|null, auto: bool}>, window: array{filed_on: string|null, ends_on: string|null, days_left: int|null, months: int}, readiness: array{percent: int|null, national: int|null, state: int|null, line: int, ready: bool, simulations: int, needed: int}, exam: array{vendor: string|null, book_url: string|null, on: string|null, time: string|null, centre: string|null, days_to: int|null, directions_url: string|null}, kit: array{items: list<array{key: 'id'|'confirmation'|'final_simulation'|'flashcards'|'sleep', done: bool}>, rules: string|null}, results: array{national: array{passed: bool|null, attempts: int, failed: int, on: string|null}, state: array{passed: bool|null, attempts: int, failed: int, on: string|null}, can_enter: bool, attempts_allowed: int, passed_both: bool}, retake: array{portions: list<'national'|'state'>, topics: list<string>, more_education: bool, attempts_left: array{national?: int, state?: int}}|null}}
     */
    public function kit(Request $request, string $item, #[CurrentUser] User $user, LicenceJourneys $journeys): JsonResponse
    {
        $request->validate(['done' => ['required', 'boolean']]);
        $journeys->tick($user, $item, $request->boolean('done'));

        return response()->json(['data' => $journeys->show($user)]);
    }

    /**
     * Enter my exam result.
     *
     * Passed or didn't per portion (null: not taken this time). A passed portion is kept; a missed one gets the retake plan, built from the weak areas on the score report.
     *
     * @response array{data: array{state: array{code: string, name: string}, steps: list<array{code: string, title: string, detail: string, url: string|null, done_on: string|null, auto: bool}>, window: array{filed_on: string|null, ends_on: string|null, days_left: int|null, months: int}, readiness: array{percent: int|null, national: int|null, state: int|null, line: int, ready: bool, simulations: int, needed: int}, exam: array{vendor: string|null, book_url: string|null, on: string|null, time: string|null, centre: string|null, days_to: int|null, directions_url: string|null}, kit: array{items: list<array{key: 'id'|'confirmation'|'final_simulation'|'flashcards'|'sleep', done: bool}>, rules: string|null}, results: array{national: array{passed: bool|null, attempts: int, failed: int, on: string|null}, state: array{passed: bool|null, attempts: int, failed: int, on: string|null}, can_enter: bool, attempts_allowed: int, passed_both: bool}, retake: array{portions: list<'national'|'state'>, topics: list<string>, more_education: bool, attempts_left: array{national?: int, state?: int}}|null}}
     */
    public function result(Request $request, #[CurrentUser] User $user, LicenceJourneys $journeys): JsonResponse
    {
        $request->validate([
            'national' => ['present', 'nullable', 'boolean'],
            'state' => ['present', 'nullable', 'boolean'],
            'weak_topics' => ['present', 'array', 'max:6'],
            'weak_topics.*' => ['string', 'in:agency,contracts,ownership,finance,math,state_rules'],
        ]);
        $topics = [];

        foreach ($request->array('weak_topics') as $topic) {
            if (is_string($topic)) {
                $topics[] = $topic;
            }
        }

        $journeys->enter($user, [
            'national' => $request->input('national') === null ? null : $request->boolean('national'),
            'state' => $request->input('state') === null ? null : $request->boolean('state'),
        ], $topics);

        return response()->json(['data' => $journeys->show($user)]);
    }
}
