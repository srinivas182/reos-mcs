<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Billing\PaymentWebhooks;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `POST /webhooks/stripe`: Stripe says a checkout, renewal, failure, cancellation or refund happened (B5.1). Signed; each event once. */
final class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentWebhooks $webhooks): JsonResponse
    {
        return match ($webhooks->receive($request->getContent(), $request->header('Stripe-Signature'))) {
            PaymentWebhooks::ACCEPTED => response()->json(['received' => true]),
            PaymentWebhooks::DUPLICATE => response()->json(['received' => true, 'duplicate' => true]),
            default => response()->json(['received' => false], 400),
        };
    }
}
