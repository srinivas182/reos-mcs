<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Billing\Payouts\ConnectedAccounts;
use App\Billing\Payouts\PayoutProviders;
use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;
use App\Models\WebhookReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /webhooks/stripe-connect` (B5.2b): Stripe says a connected account
 * changed (finished onboarding, needs more information, payouts paused).
 * Signed with the Connect endpoint's secret; each event handled once.
 */
final class ConnectWebhookController extends Controller
{
    public function __invoke(Request $request, PayoutProviders $providers, ConnectedAccounts $accounts): JsonResponse
    {
        $event = $providers->current()?->event($request->getContent(), $request->header('Stripe-Signature'));

        if ($event === null) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        [$id, $accountId] = $event;
        $new = WebhookReceipt::query()->insertOrIgnore(['integration_id' => PayoutProviders::ID, 'delivery_key' => mb_substr($id, 0, 255), 'received_at' => now()]);

        if ($new > 0 && $accountId !== null) {
            $account = ConnectedAccount::query()->where('stripe_account_id', $accountId)->first();

            if ($account !== null) {
                $accounts->refresh($account);
            }
        }

        return response()->json(['received' => true]);
    }
}
