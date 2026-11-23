<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Ask;

use App\Ask\Ask;
use App\Ask\AskPackage;
use App\Ask\InstructorQuestions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ask\AskRequest;
use App\Models\AskThread;
use App\Models\User;
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
     * @response array{data: array{uuid: string, title: string, updated_at: string|null, instructor: 'waiting'|'answered'|null, messages: list<array{id: int, role: 'question'|'answer'|'instructor', body: string, tier: 'content'|'sponsored'|'limited'|null, mode: string|null, sources: list<array{lesson_id: int, lesson: string, seconds: int|null, page: int|null, heading: string|null, text: string}>, quiz: array{prompt: string, options: list<string>, correct: int, explanation: string}|null, math_kind: string|null, sponsor: string|null, created_at: string|null}>}}
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
     * @response array{data: array{uuid: string, title: string, updated_at: string|null, instructor: 'waiting'|'answered'|null, messages: list<array{id: int, role: 'question'|'answer'|'instructor', body: string, tier: 'content'|'sponsored'|'limited'|null, mode: string|null, sources: list<array{lesson_id: int, lesson: string, seconds: int|null, page: int|null, heading: string|null, text: string}>, quiz: array{prompt: string, options: list<string>, correct: int, explanation: string}|null, math_kind: string|null, sponsor: string|null, created_at: string|null}>}}
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
     * Screen k10: answers from the course are free; answers beyond it use this month's free credits, then the sponsor's pool, then credits bought on reos.com.
     *
     * @response array{data: array{candidate: bool, beyond_course: bool, free_left: int|null, free_monthly: int|null, sponsor: string|null, credits_left: int, cost: int}}
     */
    public function package(#[CurrentUser] User $user, AskPackage $package): JsonResponse
    {
        abort_unless($user->can('learning.study'), 403);

        return response()->json(['data' => $package->for($user)]);
    }

    /**
     * Ask my instructor.
     *
     * Sends the conversation's last question, with what the course content said, to the instructors of the course. Their answer joins the conversation.
     *
     * @status 201
     *
     * @response array{data: array{uuid: string, status: 'waiting'|'answered', course: string}}
     */
    public function instructor(string $thread, #[CurrentUser] User $user, InstructorQuestions $questions): JsonResponse
    {
        $sent = $questions->send($user, $this->find($user, $thread));

        return response()->json(['data' => ['uuid' => $sent->uuid, 'status' => $sent->status, 'course' => $sent->course->title]], 201);
    }

    private function find(User $user, string $uuid): AskThread
    {
        abort_unless($user->can('learning.study'), 403);

        return AskThread::query()->where('user_id', $user->id)->where('uuid', $uuid)->firstOrFail();
    }
}
