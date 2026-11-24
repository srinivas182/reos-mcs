<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Assistant;

use App\Assistant\AgentAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\AssistantQuestionRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The agent's AI assistant (A12.3, ADR 0049; agent screens 59 and 60). */
final class AssistantController extends Controller
{
    /**
     * Ask home.
     *
     * Whether the assistant may read my CRM, deals and calendar, the starters, and my recent chats.
     *
     * @response array{data: array{permission: bool, starters: list<'virtual_broker'|'review_contract'|'prepare_contract'|'write_ad'|'reply_emails'|'prep_appointment'>, chats: list<array{id: string, title: string, updated_at: string|null}>}}
     */
    public function home(#[CurrentUser] User $user, AgentAssistant $assistant): JsonResponse
    {
        return response()->json(['data' => $assistant->home($user)]);
    }

    /**
     * Ask the assistant.
     *
     * It picks the knowledge: my day (Today's ranked list from my CRM, deals, calendar and plan), an appointment's prep, a deal, a draft email or text to a contact (never sent by REOS), the virtual broker for policy, or a general answer. My own data is read only once I've allowed it; otherwise the answer asks (kind "permission").
     *
     * @status 201
     *
     * @response array{data: array{id: string, title: string, updated_at: string|null, messages: list<array{id: int, role: 'question'|'answer', body: string, kind: 'today'|'prep'|'deal'|'draft'|'policy'|'general'|'permission'|'none'|null, items: list<array{title: string, detail: string|null, source: string|null, at: string|null, link: string|null}>, draft: array{channel: 'email'|'text', to: string, name: string, subject: string|null, body: string, href: string}|null, broker_question: string|null, created_at: string|null}>}}
     */
    public function ask(AssistantQuestionRequest $request, #[CurrentUser] User $user, AgentAssistant $assistant): JsonResponse
    {
        $chat = $assistant->ask($user, $request->string('question')->toString(), $request->filled('chat') ? $request->string('chat')->toString() : null);

        return response()->json(['data' => AgentAssistant::row($assistant->chat($user, $chat->uuid))], 201);
    }

    /**
     * One of my chats.
     *
     * @response array{data: array{id: string, title: string, updated_at: string|null, messages: list<array{id: int, role: 'question'|'answer', body: string, kind: 'today'|'prep'|'deal'|'draft'|'policy'|'general'|'permission'|'none'|null, items: list<array{title: string, detail: string|null, source: string|null, at: string|null, link: string|null}>, draft: array{channel: 'email'|'text', to: string, name: string, subject: string|null, body: string, href: string}|null, broker_question: string|null, created_at: string|null}>}}
     */
    public function chat(string $chat, #[CurrentUser] User $user, AgentAssistant $assistant): JsonResponse
    {
        return response()->json(['data' => AgentAssistant::row($assistant->chat($user, $chat))]);
    }

    /**
     * Let the assistant read my data.
     *
     * One switch for my CRM contacts, deals and calendar; audited. Turn it off any time.
     *
     * @response array{data: array{permission: bool}}
     */
    public function permission(Request $request, #[CurrentUser] User $user, AgentAssistant $assistant): JsonResponse
    {
        $request->validate(['allowed' => ['required', 'boolean']]);
        $assistant->allow($user, $request->boolean('allowed'));

        return response()->json(['data' => ['permission' => $assistant->allowed($user)]]);
    }
}
