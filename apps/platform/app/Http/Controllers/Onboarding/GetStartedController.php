<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Enums\OnboardingPath;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\ChooseStateRequest;
use App\Http\Requests\Onboarding\QuizAnswersRequest;
use App\Http\Requests\Onboarding\WebWaitlistRequest;
use App\Http\Resources\Onboarding\QuizQuestionResource;
use App\Http\Resources\Onboarding\StateResource;
use App\Models\State;
use App\Models\User;
use App\Services\Identity\SignInOptions;
use App\Services\Onboarding\FreeQuiz;
use App\Services\Onboarding\States;
use App\Services\Onboarding\WebDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Becoming an agent on reos.com, the same path as the phone (candidate journey
 * screens 2–7): state, waitlist for states not open yet, the free quiz with no
 * account, then the score and sign-up. The draft is saved to the account at
 * sign-in ({@see WebDraft}).
 */
final class GetStartedController extends Controller
{
    public function state(Request $request, States $states, WebDraft $draft): Response
    {
        $draft->choosePath(OnboardingPath::BecomeAgent);

        return Inertia::render('get-started/state', [
            'states' => StateResource::collection($states->all())->resolve($request),
            'chosen' => $draft->state()?->code,
        ]);
    }

    public function chooseState(ChooseStateRequest $request, WebDraft $draft): RedirectResponse
    {
        $state = $request->state();

        if (! $state->status->isLive()) {
            return to_route('get-started.waitlist', $state);
        }

        $draft->choosePath(OnboardingPath::BecomeAgent);
        $draft->chooseState($state);

        return to_route('get-started.quiz');
    }

    public function waitlist(Request $request, State $state, States $states): Response|RedirectResponse
    {
        if ($state->status->isLive()) {
            return to_route('get-started');
        }

        return Inertia::render('get-started/waitlist', [
            'state' => StateResource::make($states->all()->firstWhere('code', $state->code) ?? $state)->resolve($request),
            'live' => StateResource::collection($states->all()->filter(static fn (State $s): bool => $s->status->isLive())->values())->resolve($request),
        ]);
    }

    public function joinWaitlist(WebWaitlistRequest $request, State $state, States $states): RedirectResponse
    {
        $user = $request->user();
        $states->join(
            $state,
            $user instanceof User ? $user : null,
            null,
            $request->filled('email') ? $request->string('email')->toString() : null,
        );

        return back()->with('joined', true);
    }

    public function quiz(Request $request, WebDraft $draft, FreeQuiz $quiz): Response|RedirectResponse
    {
        $state = $draft->state();

        if ($state === null) {
            return to_route('get-started');
        }

        return Inertia::render('get-started/quiz', [
            'state' => $state->name,
            'questions' => QuizQuestionResource::collection($quiz->questions($state))->resolve($request),
        ]);
    }

    public function answer(QuizAnswersRequest $request, WebDraft $draft): RedirectResponse
    {
        $draft->answer($request->answers());

        return to_route('get-started.score');
    }

    public function score(Request $request, WebDraft $draft, FreeQuiz $quiz, SignInOptions $options): Response|RedirectResponse
    {
        $answers = $draft->answers();

        if ($answers === []) {
            return to_route('get-started.quiz');
        }

        // Already signed in: keep the score on the account and go on to the plan.
        $user = $request->user();

        if ($user instanceof User) {
            $draft->attach($user);

            return to_route('get-started.setup');
        }

        $scored = $quiz->score($answers);

        return Inertia::render('get-started/score', [
            'correct' => count(array_filter($scored, static fn (array $a): bool => $a['correct'])),
            'total' => count($scored),
            'topics' => array_map(
                static fn (array $t): array => ['topic' => $t['topic']->value, 'level' => $t['level']],
                $quiz->topicsOf($scored),
            ),
            'signInOptions' => $options->available(),
        ]);
    }

    /** "Create your account" on an audience page: remember the path, then sign up. */
    public function signUp(Request $request, OnboardingPath $path, WebDraft $draft): RedirectResponse
    {
        if ($path === OnboardingPath::BecomeAgent) {
            return to_route('get-started');
        }

        $draft->choosePath($path);
        $user = $request->user();

        if ($user instanceof User) {
            $draft->attach($user);

            return to_route('dashboard');
        }

        return to_route('register');
    }
}
