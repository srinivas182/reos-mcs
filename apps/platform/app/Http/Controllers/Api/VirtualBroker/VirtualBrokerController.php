<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\VirtualBroker;

use App\Http\Controllers\Controller;
use App\Http\Requests\VirtualBroker\AskBrokerRequest;
use App\Models\User;
use App\VirtualBroker\VirtualBroker;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** The virtual broker, for agents (A12.1, ADR 0047; agent screens 61 and 62, web screen 16). */
final class VirtualBrokerController extends Controller
{
    /**
     * My virtual broker.
     *
     * The brokerage, whether its policy manual is in, how fast the broker aims to reply, and my questions (newest first). 404 when the person is not an agent at a brokerage.
     *
     * @response array{data: array{brokerage: string, manual: bool, reply_hours: int, questions: list<array{id: string, question: string, answer: string, confidence: 'high'|'medium'|'low', sources: list<array{kind: 'manual'|'faq'|'rule', title: string, citation: string|null, page: int|null, url: string|null, effective_from: string|null, excerpt: string}>, status: 'answered'|'waiting'|'confirmed'|'corrected', reason: 'unclear'|'money'|'legal'|'asked'|null, escalated_at: string|null, due_at: string|null, broker_answer: string|null, broker: string|null, answered_at: string|null, added_to_knowledge: bool, agent: string, deal: array{id: int, uuid: string, property: string}|null, created_at: string|null}>}}
     */
    public function index(#[CurrentUser] User $user, VirtualBroker $broker): JsonResponse
    {
        return response()->json(['data' => $broker->forAgent($user)]);
    }

    /**
     * Ask the virtual broker.
     *
     * Answered from the brokerage's policy manual, its FAQ and the state's rules, with a confidence level and citations; free. Money or legal questions, and answers the sources don't support, go to the broker at once (status "waiting", with the time the brokerage aims to reply by).
     *
     * @status 201
     *
     * @response array{data: array{id: string, question: string, answer: string, confidence: 'high'|'medium'|'low', sources: list<array{kind: 'manual'|'faq'|'rule', title: string, citation: string|null, page: int|null, url: string|null, effective_from: string|null, excerpt: string}>, status: 'answered'|'waiting'|'confirmed'|'corrected', reason: 'unclear'|'money'|'legal'|'asked'|null, escalated_at: string|null, due_at: string|null, broker_answer: string|null, broker: string|null, answered_at: string|null, added_to_knowledge: bool, agent: string, deal: array{id: int, uuid: string, property: string}|null, created_at: string|null}}
     */
    public function store(AskBrokerRequest $request, #[CurrentUser] User $user, VirtualBroker $broker): JsonResponse
    {
        $deal = $request->integer('deal_id');
        $question = $broker->ask($user, $request->string('question')->toString(), $deal > 0 ? $deal : null);

        return response()->json(['data' => VirtualBroker::row($broker->ownQuestion($user, $question->uuid))], 201);
    }

    /**
     * One of my questions.
     *
     * @response array{data: array{id: string, question: string, answer: string, confidence: 'high'|'medium'|'low', sources: list<array{kind: 'manual'|'faq'|'rule', title: string, citation: string|null, page: int|null, url: string|null, effective_from: string|null, excerpt: string}>, status: 'answered'|'waiting'|'confirmed'|'corrected', reason: 'unclear'|'money'|'legal'|'asked'|null, escalated_at: string|null, due_at: string|null, broker_answer: string|null, broker: string|null, answered_at: string|null, added_to_knowledge: bool, agent: string, deal: array{id: int, uuid: string, property: string}|null, created_at: string|null}}
     */
    public function show(string $question, #[CurrentUser] User $user, VirtualBroker $broker): JsonResponse
    {
        return response()->json(['data' => VirtualBroker::row($broker->ownQuestion($user, $question))]);
    }

    /**
     * Send to my broker.
     *
     * The broker gets the question and the virtual broker's answer to confirm or correct. Nothing changes for a question already with the broker or answered by them.
     *
     * @response array{data: array{id: string, question: string, answer: string, confidence: 'high'|'medium'|'low', sources: list<array{kind: 'manual'|'faq'|'rule', title: string, citation: string|null, page: int|null, url: string|null, effective_from: string|null, excerpt: string}>, status: 'answered'|'waiting'|'confirmed'|'corrected', reason: 'unclear'|'money'|'legal'|'asked'|null, escalated_at: string|null, due_at: string|null, broker_answer: string|null, broker: string|null, answered_at: string|null, added_to_knowledge: bool, agent: string, deal: array{id: int, uuid: string, property: string}|null, created_at: string|null}}
     */
    public function escalate(string $question, #[CurrentUser] User $user, VirtualBroker $broker): JsonResponse
    {
        $broker->escalate($broker->ownQuestion($user, $question));

        return response()->json(['data' => VirtualBroker::row($broker->ownQuestion($user, $question))]);
    }
}
