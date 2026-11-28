<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\FarmAreaRequest;
use App\Http\Requests\Leads\FarmSendRequest;
use App\Leads\FarmUpdates;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Farming market updates (B14.3c, ADR 0068): MLS figures for an area, checked by the compliance engine. */
final class FarmController extends Controller
{
    /**
     * My farm areas.
     *
     * @response array{data: list<array{id: string, name: string, city: string|null, postal_code: string|null, tag: string, mailing_address: string, recipients: int, last_sent_at: string|null}>}
     */
    public function index(#[CurrentUser] User $user, FarmUpdates $farm): JsonResponse
    {
        return response()->json(['data' => $farm->areas($user)]);
    }

    /**
     * Add a farm area.
     *
     * A city or a ZIP code in my MLS, the tag of the contacts who get its update, and my postal address for the emails.
     *
     * @response array{data: array{id: string, name: string, city: string|null, postal_code: string|null, tag: string, mailing_address: string, recipients: int, last_sent_at: string|null}}
     */
    public function store(FarmAreaRequest $request, #[CurrentUser] User $user, FarmUpdates $farm): JsonResponse
    {
        return response()->json(['data' => $farm->add($user, $request->area())]);
    }

    /** Remove a farm area. */
    public function destroy(string $area, #[CurrentUser] User $user, FarmUpdates $farm): Response
    {
        $farm->remove($user, $area);

        return response()->noContent();
    }

    /**
     * This month's update.
     *
     * The area's figures from my MLS over the last three months (sale prices only where the MLS allows them publicly), a draft in my language, and the compliance check.
     *
     * @response array{data: array{area: array{id: string, name: string, city: string|null, postal_code: string|null, tag: string, mailing_address: string, recipients: int, last_sent_at: string|null}, stats: array{active: int, pending: int, new_listings: int, sold: int, median_price: int|null, median_days: int|null, median_sold_price: int|null, months_supply: float|null, from: string, to: string}, subject: string, body: string, attribution: string, check: array{ok: bool, issues: list<array{code: 'broker'|'broker_size'|'team_name'|'links'|'claims'|'title'|'fair_housing'|'unclear'|'banned'|'unlicensed', severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}>}, can_send: bool, next_send_at: string|null}}
     */
    public function draft(string $area, #[CurrentUser] User $user, FarmUpdates $farm): JsonResponse
    {
        return response()->json(['data' => $farm->draft($user, $area)]);
    }

    /**
     * Send the update.
     *
     * To my contacts with the area's tag, less anyone who unsubscribed. Refused when the compliance check blocks it, or within a week of the last update.
     *
     * @response array{data: array{area: array{id: string, name: string, city: string|null, postal_code: string|null, tag: string, mailing_address: string, recipients: int, last_sent_at: string|null}, stats: array{active: int, pending: int, new_listings: int, sold: int, median_price: int|null, median_days: int|null, median_sold_price: int|null, months_supply: float|null, from: string, to: string}, subject: string, body: string, attribution: string, check: array{ok: bool, issues: list<array{code: 'broker'|'broker_size'|'team_name'|'links'|'claims'|'title'|'fair_housing'|'unclear'|'banned'|'unlicensed', severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}>}, can_send: bool, next_send_at: string|null}}
     */
    public function send(FarmSendRequest $request, string $area, #[CurrentUser] User $user, FarmUpdates $farm): JsonResponse
    {
        return response()->json(['data' => $farm->send($user, $area, $request->string('subject')->toString(), $request->string('body')->toString())]);
    }
}
