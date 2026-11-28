<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\ReferralRequest;
use App\Leads\Referrals;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Agent-to-agent referrals (B14.3b, ADR 0067): between brokers; REOS takes no fee. */
final class ReferralController extends Controller
{
    /**
     * My referrals.
     *
     * The clients I referred to other agents and the ones referred to me, newest first.
     *
     * @response array{data: array{sent: list<array{id: string, direction: 'sent'|'received', status: 'sent'|'emailed'|'accepted'|'declined'|'closed', kind: 'buyer'|'seller', client: array{name: string, email: string|null, phone: string|null}, area: string|null, note: string|null, fee_percent: int|null, from: array{name: string, broker: string|null}, to: array{name: string, email: string, broker: string|null, on_reos: bool}, contact: string|null, sent_at: string, answered_at: string|null, closed_at: string|null}>, received: list<array{id: string, direction: 'sent'|'received', status: 'sent'|'emailed'|'accepted'|'declined'|'closed', kind: 'buyer'|'seller', client: array{name: string, email: string|null, phone: string|null}, area: string|null, note: string|null, fee_percent: int|null, from: array{name: string, broker: string|null}, to: array{name: string, email: string, broker: string|null, on_reos: bool}, contact: string|null, sent_at: string, answered_at: string|null, closed_at: string|null}>}}
     */
    public function index(#[CurrentUser] User $user, Referrals $referrals): JsonResponse
    {
        return response()->json(['data' => $referrals->list($user)]);
    }

    /**
     * Refer a client.
     *
     * To an agent on REOS (they accept or decline in the app) or outside REOS (they get an email). The client must have agreed. The fee is what the two brokers agreed, paid broker to broker; REOS takes none.
     *
     * @response array{data: array{id: string, direction: 'sent'|'received', status: 'sent'|'emailed'|'accepted'|'declined'|'closed', kind: 'buyer'|'seller', client: array{name: string, email: string|null, phone: string|null}, area: string|null, note: string|null, fee_percent: int|null, from: array{name: string, broker: string|null}, to: array{name: string, email: string, broker: string|null, on_reos: bool}, contact: string|null, sent_at: string, answered_at: string|null, closed_at: string|null}}
     */
    public function store(ReferralRequest $request, #[CurrentUser] User $user, Referrals $referrals): JsonResponse
    {
        return response()->json(['data' => $referrals->send($user, $request->referral())]);
    }

    /**
     * Accept a referral.
     *
     * The client lands in my CRM with a follow-up for tomorrow and a suggested first message.
     *
     * @response array{data: array{id: string, direction: 'sent'|'received', status: 'sent'|'emailed'|'accepted'|'declined'|'closed', kind: 'buyer'|'seller', client: array{name: string, email: string|null, phone: string|null}, area: string|null, note: string|null, fee_percent: int|null, from: array{name: string, broker: string|null}, to: array{name: string, email: string, broker: string|null, on_reos: bool}, contact: string|null, sent_at: string, answered_at: string|null, closed_at: string|null}}
     */
    public function accept(string $referral, #[CurrentUser] User $user, Referrals $referrals): JsonResponse
    {
        return response()->json(['data' => $referrals->accept($user, $referral)]);
    }

    /**
     * Decline a referral.
     *
     * @response array{data: array{id: string, direction: 'sent'|'received', status: 'sent'|'emailed'|'accepted'|'declined'|'closed', kind: 'buyer'|'seller', client: array{name: string, email: string|null, phone: string|null}, area: string|null, note: string|null, fee_percent: int|null, from: array{name: string, broker: string|null}, to: array{name: string, email: string, broker: string|null, on_reos: bool}, contact: string|null, sent_at: string, answered_at: string|null, closed_at: string|null}}
     */
    public function decline(string $referral, #[CurrentUser] User $user, Referrals $referrals): JsonResponse
    {
        return response()->json(['data' => $referrals->decline($user, $referral)]);
    }

    /**
     * Mark a referral closed.
     *
     * The client bought or sold. Either agent can mark it.
     *
     * @response array{data: array{id: string, direction: 'sent'|'received', status: 'sent'|'emailed'|'accepted'|'declined'|'closed', kind: 'buyer'|'seller', client: array{name: string, email: string|null, phone: string|null}, area: string|null, note: string|null, fee_percent: int|null, from: array{name: string, broker: string|null}, to: array{name: string, email: string, broker: string|null, on_reos: bool}, contact: string|null, sent_at: string, answered_at: string|null, closed_at: string|null}}
     */
    public function close(string $referral, #[CurrentUser] User $user, Referrals $referrals): JsonResponse
    {
        return response()->json(['data' => $referrals->close($user, $referral)]);
    }
}
