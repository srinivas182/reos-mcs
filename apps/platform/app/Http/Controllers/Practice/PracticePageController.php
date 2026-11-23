<?php

declare(strict_types=1);

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Models\TestAttempt;
use App\Models\User;
use App\Practice\DiagnosticBank;
use App\Practice\PracticeAccess;
use App\Practice\PracticeCoach;
use App\Practice\Readiness;
use App\Practice\Tests;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Practice and exams on the web (A8.3; candidate web nav, screens 7 and 8):
 * the hub, a timed test (answers saved after every click through the JSON
 * route the phone uses), and the report. Candidates only.
 */
final class PracticePageController extends Controller
{
    public function index(#[CurrentUser] User $user, PracticeAccess $access, Readiness $readiness, PracticeCoach $coach): Response
    {
        $access->allow($user);

        return Inertia::render('practice/index', ['tests' => $access->hub($user), 'readiness' => $readiness->of($user), 'weights' => DiagnosticBank::WEIGHTS, 'coach' => $coach->hub($user)]);
    }

    public function start(Request $request, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): RedirectResponse
    {
        $access->allow($user);
        $data = $request->validate([
            'kind' => ['required', Rule::in([TestAttempt::DIAGNOSTIC, TestAttempt::TOPIC, TestAttempt::DRILL, ...TestAttempt::PORTION_KINDS])],
            'topic' => ['required_if:kind,topic', 'nullable', Rule::in(array_keys(DiagnosticBank::WEIGHTS))],
            'mode' => ['nullable', Rule::in(['exam', 'practice'])],
        ]);
        $attempt = $tests->start($user, (string) $data['kind'], in_array($data['kind'], [TestAttempt::TOPIC, TestAttempt::DRILL], true) && is_string($data['topic'] ?? null) ? $data['topic'] : null, is_string($data['mode'] ?? null) ? $data['mode'] : null);

        return to_route('practice.attempt', $attempt->id);
    }

    public function attempt(int $attempt, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): Response|RedirectResponse
    {
        $found = $access->attempt($user, $attempt);

        if ($tests->expire($found)) {
            return to_route('practice.results', $found->id);
        }

        return Inertia::render('practice/test', ['attempt' => $tests->show($found, $user->locale->value), 'pass_line' => Tests::PASS]);
    }

    public function submit(int $attempt, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests): RedirectResponse
    {
        // A simulation's first portion moves on to the next; the last one submits (A9.1).
        $found = $tests->finish($access->attempt($user, $attempt));

        return $found->submitted_at === null ? to_route('practice.attempt', $attempt) : to_route('practice.results', $attempt);
    }

    /** Readiness (candidate web sidebar; phone screens 54 and 44, A9.2): each portion's pass chance, the booking line, and every topic. */
    public function readiness(#[CurrentUser] User $user, PracticeAccess $access, Readiness $readiness): Response
    {
        $access->allow($user);

        return Inertia::render('practice/readiness', ['map' => $readiness->map($user)]);
    }

    public function results(int $attempt, #[CurrentUser] User $user, PracticeAccess $access, Tests $tests, Readiness $readiness): Response|RedirectResponse
    {
        $found = $access->attempt($user, $attempt);

        if (! $tests->expire($found)) {
            return to_route('practice.attempt', $found->id);
        }

        return Inertia::render('practice/results', ['results' => $tests->results($found, $user->locale->value), 'readiness' => $readiness->of($user)]);
    }
}
