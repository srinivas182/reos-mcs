<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Brokerage\BrokerageSetup;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** The brokerage's setup checklist (A15.1a): from the TREC check to live in about 15 minutes. */
final class BrokerageSetupController extends Controller
{
    /**
     * Setup checklist.
     *
     * Each step (TREC check, agents, seats, policy manual, advertising policy, mentors) with whether it's done and where to do it. For the brokerage's brokers only.
     *
     * @response array{data: array{trec_number: string|null, done: int, total: int, steps: list<array{key: 'verify'|'agents'|'seats'|'manual'|'advertising'|'mentors', done: bool, href: string}>}}
     */
    public function __invoke(#[CurrentUser] User $user, ActiveRole $roles, BrokerageSetup $setup): JsonResponse
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && BrokerageSetup::shows($user, $membership->organisation, $membership->role), 404);

        return response()->json(['data' => $setup->for($membership->organisation)]);
    }
}
