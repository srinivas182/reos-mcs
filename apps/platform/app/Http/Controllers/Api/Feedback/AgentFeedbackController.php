<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Feedback;

use App\Feedback\AgentFeedbacks;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\RateAgentRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Feedback from agents after verified closings (A13.3b, ADR 0056; agent screen 125). */
final class AgentFeedbackController extends Controller
{
    /**
     * Feedback.
     *
     * Agents I closed with and can rate, and my own feedback: nothing shows until three agents have rated me; then the averages, the comments (no names), and a tip.
     *
     * @response array{data: array{to_rate: list<array{deal: string, property: string, closed_at: string|null, agent: array{id: int, name: string}}>, mine: array{count: int, needed: int, shown: bool, average: array{overall: float, communication: float, responsiveness: float, professionalism: float}|null, items: list<array{id: string, month: string, communication: int, responsiveness: int, professionalism: int, comment: string|null, response: string|null, reported: bool}>, tip: array{dimension: 'communication'|'responsiveness'|'professionalism', text: string, engine: 'claude'|'template'}|null, public: bool}}}
     */
    public function index(#[CurrentUser] User $user, AgentFeedbacks $feedback): JsonResponse
    {
        return response()->json(['data' => self::view($user, $feedback)]);
    }

    /**
     * Rate an agent.
     *
     * Rates the other side's agent on a closed deal of mine (they must be a REOS agent on the deal's parties), once.
     *
     * @response array{data: array{to_rate: list<array{deal: string, property: string, closed_at: string|null, agent: array{id: int, name: string}}>, mine: array{count: int, needed: int, shown: bool, average: array{overall: float, communication: float, responsiveness: float, professionalism: float}|null, items: list<array{id: string, month: string, communication: int, responsiveness: int, professionalism: int, comment: string|null, response: string|null, reported: bool}>, tip: array{dimension: 'communication'|'responsiveness'|'professionalism', text: string, engine: 'claude'|'template'}|null, public: bool}}}
     */
    public function store(RateAgentRequest $request, #[CurrentUser] User $user, AgentFeedbacks $feedback): JsonResponse
    {
        $feedback->rate($user, $request->string('deal')->toString(), $request->integer('agent'), $request->scores(), $request->comment());

        return response()->json(['data' => self::view($user, $feedback)]);
    }

    /**
     * Respond to a rating.
     *
     * @response array{data: array{to_rate: list<array{deal: string, property: string, closed_at: string|null, agent: array{id: int, name: string}}>, mine: array{count: int, needed: int, shown: bool, average: array{overall: float, communication: float, responsiveness: float, professionalism: float}|null, items: list<array{id: string, month: string, communication: int, responsiveness: int, professionalism: int, comment: string|null, response: string|null, reported: bool}>, tip: array{dimension: 'communication'|'responsiveness'|'professionalism', text: string, engine: 'claude'|'template'}|null, public: bool}}}
     */
    public function respond(Request $request, string $feedback, #[CurrentUser] User $user, AgentFeedbacks $feedbacks): JsonResponse
    {
        $request->validate(['response' => ['required', 'string', 'min:2', 'max:500']]);
        $feedbacks->respond($user, $feedback, $request->string('response')->toString());

        return response()->json(['data' => self::view($user, $feedbacks)]);
    }

    /**
     * Report a rating.
     *
     * Reported ratings leave the average until REOS support looks. Send no reason to take the report back (Undo).
     *
     * @response array{data: array{to_rate: list<array{deal: string, property: string, closed_at: string|null, agent: array{id: int, name: string}}>, mine: array{count: int, needed: int, shown: bool, average: array{overall: float, communication: float, responsiveness: float, professionalism: float}|null, items: list<array{id: string, month: string, communication: int, responsiveness: int, professionalism: int, comment: string|null, response: string|null, reported: bool}>, tip: array{dimension: 'communication'|'responsiveness'|'professionalism', text: string, engine: 'claude'|'template'}|null, public: bool}}}
     */
    public function report(Request $request, string $feedback, #[CurrentUser] User $user, AgentFeedbacks $feedbacks): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $reason = $request->input('reason');
        $feedbacks->report($user, $feedback, is_string($reason) && trim($reason) !== '' ? $reason : null);

        return response()->json(['data' => self::view($user, $feedbacks)]);
    }

    /**
     * Show on my agent page.
     *
     * Shows my average on my public agent page (only with three ratings or more), or hides it.
     *
     * @response array{data: array{to_rate: list<array{deal: string, property: string, closed_at: string|null, agent: array{id: int, name: string}}>, mine: array{count: int, needed: int, shown: bool, average: array{overall: float, communication: float, responsiveness: float, professionalism: float}|null, items: list<array{id: string, month: string, communication: int, responsiveness: int, professionalism: int, comment: string|null, response: string|null, reported: bool}>, tip: array{dimension: 'communication'|'responsiveness'|'professionalism', text: string, engine: 'claude'|'template'}|null, public: bool}}}
     */
    public function visibility(Request $request, #[CurrentUser] User $user, AgentFeedbacks $feedback): JsonResponse
    {
        $request->validate(['public' => ['required', 'boolean']]);
        $feedback->setPublic($user, $request->boolean('public'));

        return response()->json(['data' => self::view($user, $feedback)]);
    }

    /** @return array{to_rate: list<array{deal: string, property: string, closed_at: string|null, agent: array{id: int, name: string}}>, mine: array{count: int, needed: int, shown: bool, average: array{overall: float, communication: float, responsiveness: float, professionalism: float}|null, items: list<array{id: string, month: string, communication: int, responsiveness: int, professionalism: int, comment: string|null, response: string|null, reported: bool}>, tip: array{dimension: 'communication'|'responsiveness'|'professionalism', text: string, engine: 'claude'|'template'}|null, public: bool}} */
    public static function view(User $user, AgentFeedbacks $feedback): array
    {
        return ['to_rate' => $feedback->toRate($user), 'mine' => $feedback->mine($user)];
    }
}
