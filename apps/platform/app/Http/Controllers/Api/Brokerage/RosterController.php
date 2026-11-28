<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Brokerage\BrokerageAccess;
use App\Brokerage\Roster;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\RosterContactsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bring in your agents (A15.1b, broker journey screen 3): the roster from TREC's sponsorship records. */
final class RosterController extends Controller
{
    /**
     * The brokerage's roster from TREC.
     *
     * Every agent TREC lists under the brokerage's licence today: in the brokerage, invited, on REOS, with a contact added, or still needing an email or phone.
     *
     * @response array{data: array{trec_number: string|null, counts: array{sponsored: int, member: int, invited: int, on_reos: int, contact: int, needs_contact: int}, agents: list<array{licence: string, name: string, type: string, expires_on: string|null, state: 'member'|'invited'|'on_reos'|'contact'|'needs_contact', contact: string|null}>}}
     */
    public function index(#[CurrentUser] User $user, BrokerageAccess $access, Roster $roster): JsonResponse
    {
        return response()->json(['data' => $roster->for($access->brokerage($user))]);
    }

    /**
     * Add emails or phones.
     *
     * One line per agent: the TREC licence number, then an email or phone. Lines for agents TREC doesn't list under the brokerage are refused.
     *
     * @response array{data: array{saved: int, refused: list<string>, roster: array{trec_number: string|null, counts: array{sponsored: int, member: int, invited: int, on_reos: int, contact: int, needs_contact: int}, agents: list<array{licence: string, name: string, type: string, expires_on: string|null, state: 'member'|'invited'|'on_reos'|'contact'|'needs_contact', contact: string|null}>}}}
     */
    public function contacts(RosterContactsRequest $request, #[CurrentUser] User $user, BrokerageAccess $access, Roster $roster): JsonResponse
    {
        $brokerage = $access->brokerage($user);
        $result = $roster->contacts($brokerage, $user, $request->string('lines')->toString());

        return response()->json(['data' => [...$result, 'roster' => $roster->for($brokerage)]]);
    }

    /**
     * Invite all.
     *
     * Invites every sponsored agent who isn't in the brokerage or invited yet: at their REOS account, or the email or phone added. Invitations last 14 days with one reminder; seats are checked.
     *
     * @response array{data: array{sent: int, skipped: int, needs_contact: int, roster: array{trec_number: string|null, counts: array{sponsored: int, member: int, invited: int, on_reos: int, contact: int, needs_contact: int}, agents: list<array{licence: string, name: string, type: string, expires_on: string|null, state: 'member'|'invited'|'on_reos'|'contact'|'needs_contact', contact: string|null}>}}}
     */
    public function invite(Request $request, #[CurrentUser] User $user, BrokerageAccess $access, Roster $roster): JsonResponse
    {
        $brokerage = $access->brokerage($user);
        $result = $roster->inviteAll($brokerage, $user, $user->locale->value);

        return response()->json(['data' => [...$result, 'roster' => $roster->for($brokerage)]]);
    }
}
