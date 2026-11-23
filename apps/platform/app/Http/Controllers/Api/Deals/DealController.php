<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Deals;

use App\Deals\DealAccess;
use App\Deals\Deals;
use App\Deals\DealViews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\DealRequest;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Deals (B9.1, ADR 0037): the agent's own, from transaction tools
 * (read-only, decision 3) and tracked in REOS.
 */
final class DealController extends Controller
{
    /**
     * The Deals board.
     *
     * Open deals by stage (and closed ones from the last 90 days), each with its next deadline.
     *
     * @response array{data: array{active: list<array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}}>, under_contract: list<array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}}>, closed: list<array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}}>}}
     */
    public function index(#[CurrentUser] User $user, DealAccess $access, DealViews $views): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $views->board($user)]);
    }

    /**
     * The deals pipeline.
     *
     * Under contract, active clients and pending commission (phone screen 9).
     *
     * @response array{data: array{under_contract: int, active: int, pending_commission: string, closed_this_year: int, deals: list<array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}}>}}
     */
    public function pipeline(#[CurrentUser] User $user, DealAccess $access, DealViews $views): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $views->pipeline($user)]);
    }

    /**
     * Deal alerts.
     *
     * Deadlines in the next two weeks, and deals missing documents (phone screen 10).
     *
     * @response array{data: array{deadlines: list<array{deal: array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}}, date: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}}>, missing: list<array{deal: array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}}, documents: list<string>}>}}
     */
    public function alerts(#[CurrentUser] User $user, DealAccess $access, DealViews $views): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $views->alerts($user)]);
    }

    /**
     * Transactions from my tools.
     *
     * Connections › Transactions (screen 17): each deal's stage, documents complete, next deadline and commission. Read only.
     *
     * @response array{data: list<array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}}>}
     */
    public function transactions(#[CurrentUser] User $user, DealAccess $access, DealViews $views): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $views->transactions($user)]);
    }

    /**
     * A deal.
     *
     * Parties, documents and key dates.
     *
     * @response array{data: array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}, parties: list<array{name: string, role: string, email: string|null, phone: string|null}>, files: list<array{name: string, status: string, url: string|null}>, dates: list<array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}>}}
     */
    public function show(string $deal, #[CurrentUser] User $user, DealAccess $access, DealViews $views): JsonResponse
    {
        return response()->json(['data' => $views->show($access->find($user, $deal), $user)]);
    }

    /**
     * Track a deal in REOS.
     *
     * Every key date becomes a to-do on that day, on the calendar.
     *
     * @status 201
     *
     * @response array{data: array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}, parties: list<array{name: string, role: string, email: string|null, phone: string|null}>, files: list<array{name: string, status: string, url: string|null}>, dates: list<array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}>}}
     */
    public function store(DealRequest $request, #[CurrentUser] User $user, DealAccess $access, Deals $deals, DealViews $views): JsonResponse
    {
        $access->allow($user);
        $deal = $deals->save($user, $request->values());

        return response()->json(['data' => $views->show($deal->load('dates'), $user)], 201);
    }

    /**
     * Change a REOS deal.
     *
     * Deals from a tool are changed in the tool.
     *
     * @response array{data: array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}, parties: list<array{name: string, role: string, email: string|null, phone: string|null}>, files: list<array{name: string, status: string, url: string|null}>, dates: list<array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}>}}
     */
    public function update(string $deal, DealRequest $request, #[CurrentUser] User $user, DealAccess $access, Deals $deals, DealViews $views): JsonResponse
    {
        $saved = $deals->save($user, $request->values(), $access->find($user, $deal));

        return response()->json(['data' => $views->show($saved->load('dates'), $user)]);
    }

    /**
     * Move a REOS deal to another stage.
     *
     * Dragging on the board (decision 3: REOS deals only).
     *
     * @response array{data: array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}|null, documents: array{complete: int, total: int, missing: int}}}
     */
    public function stage(string $deal, Request $request, #[CurrentUser] User $user, DealAccess $access, Deals $deals, DealViews $views): JsonResponse
    {
        $stage = $request->validate(['stage' => ['required', Rule::in(Deal::STAGES)]])['stage'];
        $moved = $deals->moveStage($access->find($user, $deal), is_string($stage) ? $stage : 'active');

        return response()->json(['data' => $views->card($moved->load('dates'), $user)]);
    }

    /** Delete a REOS deal, with its dates' to-dos. */
    public function destroy(string $deal, #[CurrentUser] User $user, DealAccess $access, Deals $deals): JsonResponse
    {
        $found = $access->find($user, $deal);
        abort_if($found->readOnly(), 422, __('deals.errors.readOnly'));
        $deals->remove($found);

        return response()->json(null, 204);
    }
}
