<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Brokerage\BrokerVerifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\BrokerLicenceRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Verify your brokerage (A15.1a, broker journey screen 2, ADR 0069): only the designated broker creates it. */
final class BrokerVerificationController extends Controller
{
    /**
     * Look up a broker licence.
     *
     * TREC's record for the licence (name, status, expiry), whether it's in my name, and the brokerages I'm the designated broker of, with the number of agents TREC lists under each.
     *
     * @response array{data: array{licence: array{number: string, name: string, status: string|null, active: bool, expires_on: string|null}, name_matches: bool, brokerages: list<array{number: string, name: string, kind: 'entity'|'individual', sponsored: int, on_reos: bool}>}}
     */
    public function lookup(BrokerLicenceRequest $request, #[CurrentUser] User $user, BrokerVerifications $verifications): JsonResponse
    {
        return response()->json(['data' => $verifications->lookup($user, $request->string('licence')->toString())]);
    }

    /**
     * Confirm the brokerage and my role.
     *
     * A designated broker gets a verification to start the brokerage with (valid for a day). An office admin or team leader gets none: the designated broker invites them.
     *
     * @response array{data: array{verification: string|null, role: 'designated_broker'|'office_admin'|'team_leader', brokerage: string|null, sponsored: int|null}}
     */
    public function confirm(BrokerLicenceRequest $request, #[CurrentUser] User $user, BrokerVerifications $verifications): JsonResponse
    {
        $role = $request->string('role')->toString();
        $verification = $verifications->confirm($user, $request->string('licence')->toString(), $request->string('brokerage')->toString(), $role);

        return response()->json(['data' => [
            'verification' => $verification?->uuid, 'role' => $role,
            'brokerage' => $verification?->brokerage_name, 'sponsored' => $verification?->sponsored,
        ]]);
    }
}
