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
     * Readiness (the lower portion's predicted pass chance, A9.2) and each
     * portion's, whether booking is unlocked, days to the exam, the streak,
     * Continue, and today's tasks
     * (never more than the minutes chosen, the diagnostic day aside), each
     * with why. Rebuilds the plan first if a day was missed.
     *
     * @response array{data: array{readiness: int|null, portions: array{national: int|null, state: int|null}, ready_to_book: bool, topics: array<string, int|null>, weakest: list<string>, exam_on: string, days_to_exam: int, streak: int, minutes_per_day: int, reminder_time: string|null, diagnostic_done: bool, continue: array{lesson_id: int, title: string, course: string, position: int}|null, tasks: list<array{id: int, kind: 'diagnostic'|'lesson'|'practice'|'review', why: 'next'|'weak'|'review'|'catch_up'|'baseline', minutes: int, done: bool, day: string, lesson: array{id: int, title: string, course: string}|null, topic: string|null}>, behind_minutes: int, plan: array{id: int, reason: string, built_at: string|null}}}
     */
    public function today(#[CurrentUser] User $user, PracticeAccess $access, CandidateToday $today): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $today->for($user)]);
    }

    /**
     * The practice hub.
     *
     * The diagnostic, the National and state portion tests, the full
     * simulation and a quiz per topic, each with the best and last score
     * and any attempt still open.
     *
     * @response array{data: list<array{kind: 'diagnostic'|'topic'|'national'|'state'|'simulation', topic: string|null, questions: int, minutes: int|null, best: int|null, last: int|null, open: int|null, last_attempt: int|null}>}
     */
    public function index(#[CurrentUser] User $user, PracticeAccess $access): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $access->hub($user)]);
    }

    /**
     * Start a test.
     *
     * The diagnostic (50 questions, 45 minutes, results at the end), a
     * topic quiz (10 questions, why after each answer), or (A9.1) the
     * National or state portion test or the full simulation in the state's
     * real format, in `exam` mode (feedback at the end, no pause) or
     * `practice` mode; or (A9.2b) the smart drill: 15 questions from the
     * three weakest topics and recent misses, with Hint and why after each
     * answer (a `topic` makes it a 10-question topic drill). An attempt of
     * the same kind still open is returned instead.
     *
     * @status 201
     *
     * @response array{data: array{id: int, kind: string, mode: string, topic: string|null, total: int, answered: int, started_at: string, ends_at: string|null, seconds_left: int|null, submitted: bool, paused: bool, section: array{index: int, of: int, portion: string, minutes: int, questions: int}|null, questions: list<array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, flagged: bool, feedback: array{correct: bool, right: int, explanation: string, source: string}|null}>}}
     */
    public function store(Request $request, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        $access->allow($user);
        $data = $request->validate([
            'kind' => ['required', Rule::in([TestAttempt::DIAGNOSTIC, TestAttempt::TOPIC, TestAttempt::DRILL, ...TestAttempt::PORTION_KINDS])],
            'topic' => ['required_if:kind,topic', 'nullable', Rule::in(array_keys(DiagnosticBank::WEIGHTS))],
            'mode' => ['nullable', Rule::in(['exam', 'practice'])],
        ]);
        $attempt = $tests->start($user, (string) $data['kind'], in_array($data['kind'], [TestAttempt::TOPIC, TestAttempt::DRILL], true) && is_string($data['topic'] ?? null) ? $data['topic'] : null, is_string($data['mode'] ?? null) ? $data['mode'] : null);

        return response()->json(['data' => $tests->show($attempt, $user->locale->value)], $attempt->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * A test in progress.
     *
     * Its questions with my answers and flags, and the time left on the
     * server's clock. A timed test whose time ran out is scored as it stood.
     *
     * @response array{data: array{id: int, kind: string, mode: string, topic: string|null, total: int, answered: int, started_at: string, ends_at: string|null, seconds_left: int|null, submitted: bool, paused: bool, section: array{index: int, of: int, portion: string, minutes: int, questions: int}|null, questions: list<array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, flagged: bool, feedback: array{correct: bool, right: int, explanation: string, source: string}|null}>}}
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
     * @response array{data: array{id: int, kind: string, mode: string, score: int, correct: int, total: int, pass_line: int, passed: bool|null, previous: int|null, seconds: int, portions: list<array{portion: string, correct: int, total: int, pass: int, passed: bool, percent: int, previous: int|null, seconds: int, pace: int}>, timing: array{seconds_per_question: int, pace: int}|null, topics: list<array{topic: string, correct: int, total: int, percent: int, weight: int}>, weakest: list<string>, strongest: list<string>, review: list<array{id: int, n: int, topic: string, portion: string, prompt: string, options: list<string>, choice: int|null, right: int, correct: bool, scored: bool, explanation: string, source: string, lesson: array{id: int, seconds: int|null}|null}>}}
     */
    public function submit(int $attempt, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        $found = $tests->submit($access->attempt($user, $attempt));

        return response()->json(['data' => $tests->results($found, $user->locale->value)]);
    }

    /**
     * Pause or resume.
     *
     * Practice mode only: the clock stops while paused. 422 in exam mode.
     *
     * @response array{data: array{id: int, kind: string, mode: string, topic: string|null, total: int, answered: int, started_at: string, ends_at: string|null, seconds_left: int|null, submitted: bool, paused: bool, section: array{index: int, of: int, portion: string, minutes: int, questions: int}|null, questions: list<array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, flagged: bool, feedback: array{correct: bool, right: int, explanation: string, source: string}|null}>}}
     */
    public function pause(Request $request, int $attempt, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        $paused = (bool) $request->validate(['paused' => ['required', 'boolean']])['paused'];

        return response()->json(['data' => $tests->show($tests->pause($access->attempt($user, $attempt), $paused), $user->locale->value)]);
    }

    /**
     * Finish this portion.
     *
     * In a simulation, the next portion starts now with its own clock; the
     * last portion submits the test (then `submitted` is true).
     *
     * @response array{data: array{id: int, kind: string, mode: string, topic: string|null, total: int, answered: int, started_at: string, ends_at: string|null, seconds_left: int|null, submitted: bool, paused: bool, section: array{index: int, of: int, portion: string, minutes: int, questions: int}|null, questions: list<array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, flagged: bool, feedback: array{correct: bool, right: int, explanation: string, source: string}|null}>}}
     */
    public function finish(int $attempt, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        return response()->json(['data' => $tests->show($tests->finish($access->attempt($user, $attempt)), $user->locale->value)]);
    }

    /**
     * A similar question.
     *
     * One more on the same topic, not in this test, with its answer and why
     * (answer review). `data` is null when there's none.
     *
     * @response array{data: array{id: int, topic: string, prompt: string, options: list<string>, right: int, explanation: string, source: string}|null}
     */
    public function similar(int $attempt, int $question, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        $found = $access->attempt($user, $attempt);
        abort_unless($tests->expire($found), 409, __('practice.errors.stillOpen'));

        return response()->json(['data' => $tests->similar($found, $question, $user->locale->value)]);
    }

    /**
     * A test's results.
     *
     * Score and pass line, the change since last time, topics weakest first
     * with their exam weight, and every answer with why and its source. 409
     * while the test is still open.
     *
     * @response array{data: array{id: int, kind: string, mode: string, score: int, correct: int, total: int, pass_line: int, passed: bool|null, previous: int|null, seconds: int, portions: list<array{portion: string, correct: int, total: int, pass: int, passed: bool, percent: int, previous: int|null, seconds: int, pace: int}>, timing: array{seconds_per_question: int, pace: int}|null, topics: list<array{topic: string, correct: int, total: int, percent: int, weight: int}>, weakest: list<string>, strongest: list<string>, review: list<array{id: int, n: int, topic: string, portion: string, prompt: string, options: list<string>, choice: int|null, right: int, correct: bool, scored: bool, explanation: string, source: string, lesson: array{id: int, seconds: int|null}|null}>}}
     */
    public function results(int $attempt, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): JsonResponse
    {
        $found = $access->attempt($user, $attempt);
        abort_unless($tests->expire($found), 409, __('practice.errors.stillOpen'));

        return response()->json(['data' => $tests->results($found, $user->locale->value)]);
    }
}
