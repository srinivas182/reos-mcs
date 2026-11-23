<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Practice;

use App\Http\Controllers\Controller;
use App\Models\TestAttempt;
use App\Models\User;
use App\Practice\CandidateToday;
use App\Practice\DiagnosticBank;
use App\Practice\PracticeAccess;
use App\Practice\Tests;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Candidate practice on the phone (A8.3, ADR 0032): Today's plan, the
 * practice hub, and timed tests saved after every answer. Candidates only.
 */
final class PracticeController extends Controller
{
    /**
     * Today for a candidate.
     *
     * Readiness and days to the exam, the streak, Continue, and today's tasks
     * (never more than the minutes chosen, the diagnostic day aside), each
     * with why. Rebuilds the plan first if a day was missed.
     *
     * @response array{data: array{readiness: int|null, topics: array<string, int|null>, weakest: list<string>, exam_on: string, days_to_exam: int, streak: int, minutes_per_day: int, reminder_time: string|null, diagnostic_done: bool, continue: array{lesson_id: int, title: string, course: string, position: int}|null, tasks: list<array{id: int, kind: 'diagnostic'|'lesson'|'practice'|'review', why: 'next'|'weak'|'review'|'catch_up'|'baseline', minutes: int, done: bool, day: string, lesson: array{id: int, title: string, course: string}|null, topic: string|null}>, behind_minutes: int, plan: array{id: int, reason: string, built_at: string|null}}}
     */
    public function today(#[CurrentUser] User $user, PracticeAccess $access, CandidateToday $today): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $today->for($user)]);
    }

    /**
     * The practice hub.
     *
     * The diagnostic and a quiz per topic, each with the best and last score
     * and any attempt still open.
     *
     * @response array{data: list<array{kind: 'diagnostic'|'topic', topic: string|null, questions: int, minutes: int|null, best: int|null, last: int|null, open: int|null, last_attempt: int|null}>}
     */
    public function index(#[CurrentUser] User $user, PracticeAccess $access): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $access->hub($user)]);
    }

    /**
     * Start a test.
     *
     * The diagnostic (50 questions, 45 minutes, results at the end) or a
     * topic quiz (10 questions, why after each answer). An attempt of the
     * same kind still open is returned instead.
     *
     * @status 201
     *
     * @response array{data: array{id: int, kind: string, mode: string, topic: string|null, total: int, answered: int, started_at: string, ends_at: string|null, seconds_left: int|null, submitted: bool, questions: list<array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, flagged: bool, feedback: array{correct: bool, right: int, explanation: string, source: string}|null}>}}
     */
    public function store(Request $request, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        $access->allow($user);
        $data = $request->validate([
            'kind' => ['required', Rule::in([TestAttempt::DIAGNOSTIC, TestAttempt::TOPIC])],
            'topic' => ['required_if:kind,topic', 'nullable', Rule::in(array_keys(DiagnosticBank::WEIGHTS))],
        ]);
        $attempt = $tests->start($user, (string) $data['kind'], $data['kind'] === TestAttempt::TOPIC && is_string($data['topic'] ?? null) ? $data['topic'] : null);

        return response()->json(['data' => $tests->show($attempt, $user->locale->value)], $attempt->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * A test in progress.
     *
     * Its questions with my answers and flags, and the time left on the
     * server's clock. A timed test whose time ran out is scored as it stood.
     *
     * @response array{data: array{id: int, kind: string, mode: string, topic: string|null, total: int, answered: int, started_at: string, ends_at: string|null, seconds_left: int|null, submitted: bool, questions: list<array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, flagged: bool, feedback: array{correct: bool, right: int, explanation: string, source: string}|null}>}}
     */
    public function show(int $attempt, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        return response()->json(['data' => $tests->show($access->attempt($user, $attempt), $user->locale->value)]);
    }

    /**
     * Save an answer.
     *
     * Saved the moment it's tapped, so closing the app loses nothing. In a
     * topic quiz the answer comes back with why. 422 once the test has ended.
     *
     * @response array{data: array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, flagged: bool, feedback: array{correct: bool, right: int, explanation: string, source: string}|null}}
     */
    public function answer(Request $request, int $attempt, int $question, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        $found = $access->attempt($user, $attempt);
        $data = $request->validate(['choice' => ['nullable', 'integer', 'min:0', 'max:5'], 'flagged' => ['sometimes', 'boolean']]);

        return response()->json(['data' => $tests->answer($found, $question, is_numeric($data['choice'] ?? null) ? (int) $data['choice'] : null, (bool) ($data['flagged'] ?? false), $user->locale->value)]);
    }

    /**
     * Submit a test.
     *
     * Scored by topic; readiness and the study plan update at once.
     *
     * @response array{data: array{id: int, kind: string, score: int, correct: int, total: int, pass_line: int, previous: int|null, seconds: int, topics: list<array{topic: string, correct: int, total: int, percent: int, weight: int}>, weakest: list<string>, strongest: list<string>, review: list<array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, right: int, correct: bool, explanation: string, source: string}>}}
     */
    public function submit(int $attempt, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        $found = $tests->submit($access->attempt($user, $attempt));

        return response()->json(['data' => $tests->results($found, $user->locale->value)]);
    }

    /**
     * A test's results.
     *
     * Score and pass line, the change since last time, topics weakest first
     * with their exam weight, and every answer with why and its source. 409
     * while the test is still open.
     *
     * @response array{data: array{id: int, kind: string, score: int, correct: int, total: int, pass_line: int, previous: int|null, seconds: int, topics: list<array{topic: string, correct: int, total: int, percent: int, weight: int}>, weakest: list<string>, strongest: list<string>, review: list<array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, right: int, correct: bool, explanation: string, source: string}>}}
     */
    public function results(int $attempt, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        $found = $access->attempt($user, $attempt);
        abort_unless($tests->expire($found), 409, __('practice.errors.stillOpen'));

        return response()->json(['data' => $tests->results($found, $user->locale->value)]);
    }
}
