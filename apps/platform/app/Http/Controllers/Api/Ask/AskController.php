<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Ask;

use App\Ask\Ask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ask\AskRequest;
use App\Models\AskThread;
use App\Models\User;
use App\Services\Ai\AiCredits;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Ask, the study mate (A10.1, candidate screen 13; ADR 0039): answers from
 * the person's courses first, with the lesson and second to watch.
 */
final class AskController extends Controller
{
    /**
     * My conversations.
     *
     * The 30 most recent, newest first.
     *
     * @response array{data: list<array{uuid: string, title: string, updated_at: string|null}>}
     */
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        abort_unless($user->can('learning.study'), 403);

        return response()->json(['data' => AskThread::query()->where('user_id', $user->id)->latest('updated_at')->latest('id')->limit(30)->get()
            ->map(static fn (AskThread $t): array => ['uuid' => $t->uuid, 'title' => $t->title, 'updated_at' => $t->updated_at?->toIso8601String()])->values()->all()]);
    }

    /**
     * One conversation.
     *
     * @response array{data: array{uuid: string, title: string, updated_at: string|null, messages: list<array{id: int, role: 'question'|'answer'|'instructor', body: string, tier: 'content'|'sponsored'|'limited'|null, mode: string|null, sources: list<array{lesson_id: int, lesson: string, seconds: int|null, page: int|null, heading: string|null, text: string}>, quiz: array{prompt: string, options: list<string>, correct: int, explanation: string}|null, math_kind: string|null, sponsor: string|null, created_at: string|null}>}}
     */
    public function show(string $thread, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => Ask::thread($this->find($user, $thread))]);
    }

    /**
     * Ask a question.
     *
     * Answered from the person's courses first (free). Beyond them, a general answer only when a sponsor pays (candidates) or from the agent's credits; otherwise it says so and offers their instructor. Follow-ups (`simpler`, `spanish`, `quiz`) work on the thread's last answer.
     *
     * @status 201
     *
     * @response array{data: array{uuid: string, title: string, updated_at: string|null, messages: list<array{id: int, role: 'question'|'answer'|'instructor', body: string, tier: 'content'|'sponsored'|'limited'|null, mode: string|null, sources: list<array{lesson_id: int, lesson: string, seconds: int|null, page: int|null, heading: string|null, text: string}>, quiz: array{prompt: string, options: list<string>, correct: int, explanation: string}|null, math_kind: string|null, sponsor: string|null, created_at: string|null}>}}
     */
    public function store(AskRequest $request, #[CurrentUser] User $user, Ask $ask): JsonResponse
    {
        abort_unless($user->can('learning.study'), 403);
        $thread = $request->thread() === null ? null : $this->find($user, (string) $request->thread());

        return response()->json(['data' => Ask::thread($ask->ask($user, $request->question(), $request->mode(), $thread))], 201);
    }

    /**
     * My package and AI help.
     *
     * Screen k10: whether answers beyond the course are available, and who pays for them. Candidates never see credits.
     *
     * @response array{data: array{candidate: bool, beyond_course: bool, sponsor: string|null, credits_left: int|null}}
     */
    public function package(#[CurrentUser] User $user, AiCredits $credits): JsonResponse
    {
        abort_unless($user->can('learning.study'), 403);
        $candidate = $credits->forCandidate($user);
        $sponsor = $candidate ? $credits->sponsorFor($user) : null;

        return response()->json(['data' => [
            'candidate' => $candidate,
            'beyond_course' => $candidate ? $sponsor !== null : $credits->left($user) > 0,
            'sponsor' => $sponsor?->name,
            'credits_left' => $candidate ? null : $credits->left($user),
        ]]);
    }

    private function find(User $user, string $uuid): AskThread
    {
        abort_unless($user->can('learning.study'), 403);

        return AskThread::query()->where('user_id', $user->id)->where('uuid', $uuid)->firstOrFail();
    }
}
