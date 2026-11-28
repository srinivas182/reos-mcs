<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coordinators;

use App\Coordinators\DealShares;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coordinators\CoordinatorActionRequest;
use App\Http\Requests\Coordinators\FileMessageRequest;
use App\Http\Requests\Coordinators\ShareDealRequest;
use App\Http\Requests\Coordinators\ShareSwitchesRequest;
use App\Http\Requests\Coordinators\TickRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Coordinators (B17.1, ADR 0085): the agent shares a deal with a connected
 * transaction coordinator by part (dates, documents, parties, messages);
 * the coordinator works the file until closing, when access ends. Every
 * action is audited for the agent and the deal's brokerage.
 */
final class CoordinatorController extends Controller
{
    /**
     * The deal's coordinators.
     *
     * The agent's connected transaction coordinators, and who this deal is (or was) shared with, with the switches and when access ended.
     *
     * @response array{data: array{coordinators: list<array{id: int, name: string, business: string}>, shares: list<array{id: string, coordinator: string, switches: array{dates: bool, documents: bool, parties: bool, messages: bool}, status: string, end_reason: string|null, ended_at: string|null}>}}
     */
    public function forDeal(string $deal, #[CurrentUser] User $user, DealShares $shares): JsonResponse
    {
        return response()->json(['data' => $shares->forDeal($user, $deal)]);
    }

    /**
     * Share a deal with a coordinator.
     *
     * Only a transaction coordinator the agent is connected with. Documents and dates are on unless switched off; parties and messages are off unless switched on. Access ends at closing. 422 on a closed or cancelled deal.
     *
     * @status 201
     *
     * @response array{data: array{id: string, switches: array{dates: bool, documents: bool, parties: bool, messages: bool}}}
     */
    public function share(ShareDealRequest $request, string $deal, #[CurrentUser] User $user, DealShares $shares): JsonResponse
    {
        $share = $shares->share($user, $deal, $request->integer('coordinator_id'), $request->switches());

        return response()->json(['data' => ['id' => $share->uuid, 'switches' => DealShares::switches($share)]], 201);
    }

    /**
     * Change what the coordinator sees.
     *
     * @response array{data: array{id: string, switches: array{dates: bool, documents: bool, parties: bool, messages: bool}}}
     */
    public function switches(ShareSwitchesRequest $request, string $share, #[CurrentUser] User $user, DealShares $shares): JsonResponse
    {
        $updated = $shares->setSwitches($user, $share, $request->switches());

        return response()->json(['data' => ['id' => $updated->uuid, 'switches' => DealShares::switches($updated)]]);
    }

    /**
     * Remove the coordinator.
     *
     * Their access ends now.
     *
     * @response array{removed: bool}
     */
    public function remove(CoordinatorActionRequest $request, string $share, #[CurrentUser] User $user, DealShares $shares): JsonResponse
    {
        $shares->remove($user, $share);

        return response()->json(['removed' => true]);
    }

    /**
     * My files.
     *
     * The coordinator's active files, nearest deadline first: the next date, missing documents and progress. Only what each agent switched on.
     *
     * @response array{data: array{counts: array{active: int, due_this_week: int, missing: int}, files: list<array{id: string, agent: string, property: string, stage: string, shared_on: string, next: array{label: string, due_on: string}|null, missing: int, progress: array{done: int, total: int}}>}}
     */
    public function board(#[CurrentUser] User $user, DealShares $shares): JsonResponse
    {
        return response()->json(['data' => $shares->board($user)]);
    }

    /**
     * A file.
     *
     * The shared parts only: a part that's switched off is null. 404 once the deal closes or the agent removes the coordinator.
     *
     * @response array{data: array{id: string, agent: string, property: string, city: string|null, stage: string, shared_on: string, switches: array{dates: bool, documents: bool, parties: bool, messages: bool}, dates: list<array{id: string, label: string, due_on: string, done: bool}>|null, documents: list<array{name: string, status: string, done: bool}>|null, parties: list<array{name: string, role: string}>|null, draft: string, conversation: string|null}}
     */
    public function file(string $share, #[CurrentUser] User $user, DealShares $shares): JsonResponse
    {
        return response()->json(['data' => $shares->file($user, $share)]);
    }

    /**
     * Tick a checklist item.
     *
     * A document as `doc:<name>` or a date's id. The agent sees the progress.
     *
     * @response array{ticked: bool}
     */
    public function tick(TickRequest $request, string $share, #[CurrentUser] User $user, DealShares $shares): JsonResponse
    {
        $shares->tick($user, $share, $request->string('item')->toString(), $request->boolean('done'));

        return response()->json(['ticked' => true]);
    }

    /**
     * Message the agent about the file.
     *
     * Goes to a chat with the agent, so the agent always has it. Needs the messages switch (422 otherwise).
     *
     * @status 201
     *
     * @response array{data: array{conversation: string}}
     */
    public function message(FileMessageRequest $request, string $share, #[CurrentUser] User $user, DealShares $shares): JsonResponse
    {
        return response()->json(['data' => ['conversation' => $shares->message($user, $share, $request->string('body')->toString())]], 201);
    }
}
