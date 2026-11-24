<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Ce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ce\RenewalRequest;
use App\Licences\Ce\CeWallet;
use App\Models\Licence;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** The CE wallet and the renewal checklist (A11.2, ADR 0045; agent screens 103, 105, 106). */
final class CeWalletController extends Controller
{
    /**
     * My CE wallet.
     *
     * Per licence: hours against the renewal (required and electives), the completions in the period and who reported them, TREC's own view, the renewal checklist and where to get the hours still missing.
     *
     * @response array{data: list<array{licence: array{id: int, state_code: string, state_name: string, number: string, type: string, status: string, expires_on: string|null, sponsor_name: string|null}, ce: array{state: string, due_on: string|null, period_start: string|null, days_left: int|null, first_renewal: bool, hours_required: float, hours_done: float, hours_left: float, complete: bool, parts: list<array{code: string, title: string, kind: 'mandatory'|'broker_responsibility'|'qualifying'|'elective', hours: float, hours_done: float, done: bool}>, deferral: array{fee_usd: float, days: int}|null, sources: list<array{key: string, citation: string, url: string|null, effective_from: string}>}, completions: list<array{id: int, title: string, hours: float, completed_on: string, provider: string|null, course_code: string|null, status: 'reos_reported'|'self_reported'|'on_record', certificate: bool, reporting: array{status: 'waiting'|'pending_upload'|'submitted'|'reported'|'rejected', reference: string|null, reason: string|null, steps: list<array{code: 'completed'|'final_passed'|'reported'|'member_record'|'certificate', state: 'done'|'waiting'|'pending'|'rejected'|'not_connected'}>}|null}>, trec: array{mce: 'met'|'outstanding'|'none'|null, education: 'met'|'outstanding'|'none'|null, checked_at: string|null}, renewal: array{due_on: string|null, ready: bool, renewed_at: string|null, confirmed_at: string|null, renewed_until: string|null, steps: list<array{code: string, title: string, detail: string, url: string|null, done: bool}>}, buy: array{price: int, product: string, amount: int, interval: 'month'|'year'|'once'}|null, unlocked: bool}>}
     */
    public function index(#[CurrentUser] User $user, CeWallet $wallet): JsonResponse
    {
        return response()->json(['data' => $wallet->for($user)]);
    }

    /**
     * A CE certificate and its reporting steps.
     *
     * A REOS course's certificate is issued once TREC has the completion, with TREC's reference (agent screens 103 and 104).
     *
     * @response array{data: array{id: int, holder: string, licence: string|null, state_code: string, title: string, hours: float, completed_on: string, provider: string|null, course_number: string|null, status: 'reos_reported'|'self_reported'|'on_record', issued: bool, reporting: array{status: 'waiting'|'pending_upload'|'submitted'|'reported'|'rejected', reference: string|null, reason: string|null, steps: list<array{code: 'completed'|'final_passed'|'reported'|'member_record'|'certificate', state: 'done'|'waiting'|'pending'|'rejected'|'not_connected'}>}|null}}
     */
    public function certificate(int $completion, #[CurrentUser] User $user, CeWallet $wallet): JsonResponse
    {
        return response()->json(['data' => $wallet->certificate($user, $completion)]);
    }

    /**
     * Renewed with the regulator.
     *
     * The agent says they renewed (or takes it back); REOS confirms the new expiry on the regulator's record overnight.
     *
     * @response array{data: array{licence: array{id: int, state_code: string, state_name: string, number: string, type: string, status: string, expires_on: string|null, sponsor_name: string|null}, ce: array{state: string, due_on: string|null, period_start: string|null, days_left: int|null, first_renewal: bool, hours_required: float, hours_done: float, hours_left: float, complete: bool, parts: list<array{code: string, title: string, kind: 'mandatory'|'broker_responsibility'|'qualifying'|'elective', hours: float, hours_done: float, done: bool}>, deferral: array{fee_usd: float, days: int}|null, sources: list<array{key: string, citation: string, url: string|null, effective_from: string}>}, completions: list<array{id: int, title: string, hours: float, completed_on: string, provider: string|null, course_code: string|null, status: 'reos_reported'|'self_reported'|'on_record', certificate: bool, reporting: array{status: 'waiting'|'pending_upload'|'submitted'|'reported'|'rejected', reference: string|null, reason: string|null, steps: list<array{code: 'completed'|'final_passed'|'reported'|'member_record'|'certificate', state: 'done'|'waiting'|'pending'|'rejected'|'not_connected'}>}|null}>, trec: array{mce: 'met'|'outstanding'|'none'|null, education: 'met'|'outstanding'|'none'|null, checked_at: string|null}, renewal: array{due_on: string|null, ready: bool, renewed_at: string|null, confirmed_at: string|null, renewed_until: string|null, steps: list<array{code: string, title: string, detail: string, url: string|null, done: bool}>}, buy: array{price: int, product: string, amount: int, interval: 'month'|'year'|'once'}|null, unlocked: bool}|null}
     */
    public function renewal(RenewalRequest $request, int $licence, #[CurrentUser] User $user, CeWallet $wallet): JsonResponse
    {
        $found = Licence::query()->where('user_id', $user->id)->findOrFail($licence);

        return response()->json(['data' => $wallet->renewed($found, $request->boolean('renewed'))]);
    }
}
