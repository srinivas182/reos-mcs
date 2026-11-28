<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Brokerage\BrokerageAccess;
use App\Brokerage\BrokerageStaff;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\DutiesRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** People and permissions (A15.2, ADR 0071): the designated broker, office admins and team leaders, and their duties. */
final class StaffController extends Controller
{
    /**
     * Who runs the brokerage.
     *
     * Each person with the broker role in the brokerage or its teams: designated broker, office admin or team leader, with their duties.
     *
     * @response array{data: array{people: list<array{user_id: int, name: string, title: 'designated_broker'|'office_admin'|'team_leader', team: string|null, duties: list<string>, editable: bool}>, designated: bool}}
     */
    public function index(#[CurrentUser] User $user, BrokerageAccess $access, BrokerageStaff $staff): JsonResponse
    {
        $brokerage = $access->brokerage($user);

        return response()->json(['data' => ['people' => $staff->list($brokerage), 'designated' => $staff->isDesignated($brokerage, $user)]]);
    }

    /**
     * Change someone's duties.
     *
     * Approve posts, answer escalated questions, manage policies, sponsor agents. The designated broker only.
     *
     * @response array{data: array{people: list<array{user_id: int, name: string, title: 'designated_broker'|'office_admin'|'team_leader', team: string|null, duties: list<string>, editable: bool}>, designated: bool}}
     */
    public function update(DutiesRequest $request, int $member, #[CurrentUser] User $user, BrokerageAccess $access, BrokerageStaff $staff): JsonResponse
    {
        $brokerage = $access->brokerage($user);
        $staff->setDuties($brokerage, $user, $member, $request->duties());

        return response()->json(['data' => ['people' => $staff->list($brokerage), 'designated' => true]]);
    }
}
