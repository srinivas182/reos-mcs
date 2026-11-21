<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Onboarding;

use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Onboarding\QuizAttemptRequest;
use App\Http\Resources\Onboarding\QuizQuestionResource;
use App\Http\Resources\Onboarding\QuizResultResource;
use App\Models\State;
use App\Models\User;
use App\Services\Onboarding\FreeQuiz;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The free quiz before sign-up (mobile journey screens 6–7). */
final class FreeQuizController extends Controller
{
    /**
     * Get the free quiz.
     *
     * Ten exam-style questions for the state, in `locale` (en or es), each
     * with its answer, explanation and source. No account needed.
     *
     * @unauthenticated
     */
    public function show(Request $request, State $state, FreeQuiz $quiz): AnonymousResourceCollection
    {
        $locale = Locale::fromTag($request->string('locale')->toString()) ?? Locale::fromTag($request->getPreferredLanguage(['en', 'es']));
        app()->setLocale(($locale ?? Locale::English)->value);

        return QuizQuestionResource::collection($quiz->questions($state));
    }

    /**
     * Save a quiz to my account.
     *
     * The free quiz is answered before sign-up and kept on the phone; the
     * app sends it here straight after the account is created.
     */
    public function store(QuizAttemptRequest $request, #[CurrentUser] User $user, FreeQuiz $quiz): QuizResultResource
    {
        $state = $request->filled('state') ? State::query()->find($request->string('state')->upper()->toString()) : null;

        return new QuizResultResource($quiz->record($user, $state, $request->answers()));
    }
}
