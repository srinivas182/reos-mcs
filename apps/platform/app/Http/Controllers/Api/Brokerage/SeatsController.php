<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Brokerage\BrokerageAccess;
use App\Brokerage\BrokerageSeats;
use App\Brokerage\BrokerageStaff;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\SeatsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Seats and plan (A15.1b, broker journey screen 4): who pays, and pooled AI credits. */
final class SeatsController extends Controller
{
    /**
     * Seats and who pays.
     *
     * The plan's seats and how many are used or invited, who pays, AI credits per seat and whether they're pooled, the candidate sponsorship pool and teams. Seat counts are bought on reos.com.
     *
     * @response array{data: array{seats: int|null, used: int, pending: int, left: int|null, payer: 'organisation'|'member'|'split', credits_per_seat: int, credits_pooled: bool, pool: int|null, candidate_pool: int, teams: int, chosen: bool}}
     */
    public function show(#[CurrentUser] User $user, BrokerageAccess $access, BrokerageSeats $seats): JsonResponse
    {
        return response()->json(['data' => $seats->show($access->brokerage($user))]);
    }

    /**
     * Choose who pays.
     *
     * The brokerage, each agent, or split (the brokerage pays the seat, agents their own extras), and whether AI credits are pooled across seats. The designated broker only.
     *
     * @response array{data: array{seats: int|null, used: int, pending: int, left: int|null, payer: 'organisation'|'member'|'split', credits_per_seat: int, credits_pooled: bool, pool: int|null, candidate_pool: int, teams: int, chosen: bool}}
     */
    public function update(SeatsRequest $request, #[CurrentUser] User $user, BrokerageAccess $access, BrokerageSeats $seats, BrokerageStaff $staff): JsonResponse
    {
        $staff->designatedOnly($access->brokerage($user), $user);

        return response()->json(['data' => $seats->choose($access->brokerage($user), $user, $request->string('payer')->toString(), $request->boolean('credits_pooled'))]);
    }
}
