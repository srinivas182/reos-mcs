<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Accounting;

use App\Accounting\QuickBooksExports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountingActionRequest;
use App\Http\Requests\Accounting\AccountsRequest;
use App\Licences\Journey\BrokerOffers;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * QuickBooks Online for the brokerage (B17.3, ADR 0087): connect the
 * company, map the accounts once, and post signed split sheets and payouts
 * as journal entries, once each.
 */
final class QuickBooksController extends Controller
{
    /**
     * QuickBooks.
     *
     * The connection, the company's accounts and the mapping, what's waiting to post, and the last 20 entries.
     *
     * @response array{data: array{connection: array{company: string, mode: string, status: string, connected_at: string|null, accounts: array<string, string>, mapped: bool}|null, chart: list<array{id: string, name: string, type: string}>, roles: list<string>, pending: array{split_sheets: list<array{id: string, property: string, agent: string, closing_on: string, gross_cents: int}>, payouts: list<array{id: string, payee: string, sent_on: string, amount_cents: int}>}, exports: list<array{kind: string, subject: string, amount_cents: int, status: string, ref: string|null, error: string|null, at: string}>}}
     */
    public function show(#[CurrentUser] User $user, BrokerOffers $offers, QuickBooksExports $exports): JsonResponse
    {
        return response()->json(['data' => $exports->board($offers->brokerageOf($user))]);
    }

    /**
     * Connect QuickBooks.
     *
     * Where to send the broker to sign in to Intuit; Intuit returns to REOS, which finishes the connection.
     *
     * @response array{data: array{url: string}}
     */
    public function connect(AccountingActionRequest $request, #[CurrentUser] User $user, BrokerOffers $offers, QuickBooksExports $exports): JsonResponse
    {
        return response()->json(['data' => ['url' => $exports->start($offers->brokerageOf($user), $user)]]);
    }

    /**
     * Map the accounts.
     *
     * An account from the company's chart for each role: clearing, brokerage income, team payable, agent payable, payouts expense and payouts clearing.
     *
     * @response array{data: array{mapped: bool}}
     */
    public function accounts(AccountsRequest $request, #[CurrentUser] User $user, BrokerOffers $offers, QuickBooksExports $exports): JsonResponse
    {
        $exports->map($offers->brokerageOf($user), $request->accounts(), $user);

        return response()->json(['data' => ['mapped' => true]]);
    }

    /**
     * Export now.
     *
     * Posts every signed split sheet and sent payout that hasn't posted. 422 `accounting` until connected and mapped.
     *
     * @response array{data: array{posted: int, failed: int}}
     */
    public function export(AccountingActionRequest $request, #[CurrentUser] User $user, BrokerOffers $offers, QuickBooksExports $exports): JsonResponse
    {
        return response()->json(['data' => $exports->exportAll($offers->brokerageOf($user), $user)]);
    }

    /**
     * Disconnect QuickBooks.
     *
     * Entries already posted stay in QuickBooks.
     *
     * @response array{disconnected: bool}
     */
    public function disconnect(AccountingActionRequest $request, #[CurrentUser] User $user, BrokerOffers $offers, QuickBooksExports $exports): JsonResponse
    {
        $exports->disconnect($offers->brokerageOf($user), $user);

        return response()->json(['disconnected' => true]);
    }
}
