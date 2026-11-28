<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Providers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Providers\AgentsFilterRequest;
use App\Http\Requests\Providers\SendRequestRequest;
use App\Models\User;
use App\ProviderNetwork\AgentsInArea;
use App\ProviderNetwork\ProviderRequests;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Agents in my areas and requests to connect (B16.1, ADR 0079), for a
 * live provider. Requests are limited per plan each month.
 */
final class AgentsInAreaController extends Controller
{
    /**
     * Agents in my areas.
     *
     * REOS agents working in the provider's areas (MLS listings and REOS deals in 12 months), by level: new, active or top producer. First name and initial only; never contacts. With this month's requests used and left.
     *
     * @response array{data: array{areas: list<array{id: int, label: string}>, filters: array{level: 'new'|'active'|'top'|null, area: int|null}, counts: array{new: int, active: int, top: int}, agents: list<array{id: int, name: string, brokerage: string|null, level: 'new'|'active'|'top', sides: int, cities: list<string>, request: array{id: string, status: string, sent_at: string}|null, can_request: bool}>, usage: array{tier: string|null, limit: int, used: int, left: int, resets_on: string}}}
     */
    public function index(AgentsFilterRequest $request, #[CurrentUser] User $user, AgentsInArea $area): JsonResponse
    {
        return response()->json(['data' => $area->for($user, $request->level(), $request->area())]);
    }

    /**
     * My requests to connect.
     *
     * The provider's requests, newest first: waiting, accepted, declined, expired or withdrawn.
     *
     * @response array{data: list<array{id: string, agent: string, status: string, message: string|null, sent_at: string, expires_at: string}>, usage: array{tier: string|null, limit: int, used: int, left: int, resets_on: string}}
     */
    public function requests(#[CurrentUser] User $user, ProviderRequests $requests, AgentsInArea $area): JsonResponse
    {
        return response()->json(['data' => $requests->sent($user), 'usage' => $area->usage($requests->profile($user))]);
    }

    /**
     * Ask an agent to connect.
     *
     * Counts toward the plan's monthly limit (Listed 5, Pro 25, Partner 75). The note can't hold phone numbers, email addresses or links. 422 with `limit` when the month's requests are used.
     *
     * @status 201
     *
     * @response array{data: array{id: string, status: string, expires_at: string}, usage: array{tier: string|null, limit: int, used: int, left: int, resets_on: string}}
     */
    public function send(SendRequestRequest $request, #[CurrentUser] User $user, ProviderRequests $requests, AgentsInArea $area): JsonResponse
    {
        $sent = $requests->send($user, $request->integer('agent_id'), $request->message());

        return response()->json([
            'data' => ['id' => $sent->uuid, 'status' => $sent->state(), 'expires_at' => $sent->expires_at->toIso8601String()],
            'usage' => $area->usage($requests->profile($user)),
        ], 201);
    }

    /**
     * Withdraw a waiting request.
     *
     * It still counts toward the month.
     *
     * @response array{withdrawn: bool}
     */
    public function withdraw(string $providerRequest, #[CurrentUser] User $user, ProviderRequests $requests): JsonResponse
    {
        $requests->withdraw($user, $providerRequest);

        return response()->json(['withdrawn' => true]);
    }
}
