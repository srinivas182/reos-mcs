<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Practice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\FlashcardReviewsRequest;
use App\Http\Requests\Practice\SaveFlashcardRequest;
use App\Models\User;
use App\Practice\DiagnosticBank;
use App\Practice\Flashcards\Flashcards;
use App\Practice\PracticeAccess;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Flashcards (candidate screen 31, A9.2d). */
final class FlashcardController extends Controller
{
    /**
     * My flashcards.
     *
     * The whole deck with each card's schedule, for reviewing offline, plus
     * how many are due and how many new cards are left today. The course's
     * approved flashcards for lessons started are added first. `topic`
     * keeps to one topic.
     *
     * @response array{data: array{due: int, new_left: int, total: int, cards: list<array{id: int, source: string, topic: string|null, front: string, back: string, new: bool, due_at: string|null, reps: int, lapses: int, stability: float|null, difficulty: float|null, last_reviewed_at: string|null}>}}
     */
    public function index(Request $request, #[CurrentUser] User $user, PracticeAccess $access, Flashcards $cards): JsonResponse
    {
        $access->allow($user);
        $topic = $request->query('topic');

        return response()->json(['data' => $cards->deck($user, $user->locale->value, is_string($topic) && array_key_exists($topic, DiagnosticBank::WEIGHTS) ? $topic : null)]);
    }

    /**
     * Save as flashcard.
     *
     * From a check-in, an Ask answer, or answer review (with the question,
     * saved once). The card is due at once.
     *
     * @status 201
     *
     * @response array{data: array{id: int, source: string, topic: string|null, front: string, back: string, new: bool, due_at: string|null, reps: int, lapses: int, stability: float|null, difficulty: float|null, last_reviewed_at: string|null}}
     */
    public function store(SaveFlashcardRequest $request, #[CurrentUser] User $user, PracticeAccess $access, Flashcards $cards): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $cards->save($user, $request->text('source'), $request->text('front'), $request->text('back'), $user->locale->value, $request->topic(), $request->questionId())], 201);
    }

    /**
     * Review flashcards.
     *
     * Again, Hard or Got it (1, 2 or 3) for one card, or the phone's offline
     * queue. Each review has its own id and counts once; they apply oldest
     * first. Returns the cards as now scheduled.
     *
     * @response array{data: array{applied: int, cards: list<array{id: int, source: string, topic: string|null, front: string, back: string, new: bool, due_at: string|null, reps: int, lapses: int, stability: float|null, difficulty: float|null, last_reviewed_at: string|null}>}}
     */
    public function reviews(FlashcardReviewsRequest $request, #[CurrentUser] User $user, PracticeAccess $access, Flashcards $cards): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $cards->review($user, $request->reviews(), $user->locale->value)]);
    }
}
