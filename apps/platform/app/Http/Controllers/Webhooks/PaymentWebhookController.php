<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Billing\PaymentWebhooks;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /webhooks/{stripe|paypal|authorize-net}`: a gateway says a checkout,
 * renewal, failure, cancellation or refund happened (B5.1, B5.2a). Each is
 * signed its own way; each event is handled once.
 */
final class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentWebhooks $webhooks): JsonResponse
    {
        $signature = match ($gateway) {
            'stripe' => $request->header('Stripe-Signature'),
            'authorize-net' => $request->header('X-ANET-Signature'),
            default => null,
        };

        return match ($webhooks->receive($gateway, $request->getContent(), $signature)) {
            PaymentWebhooks::ACCEPTED => response()->json(['received' => true]),
            PaymentWebhooks::DUPLICATE => response()->json(['received' => true, 'duplicate' => true]),
            default => response()->json(['received' => false], 400),
        };
    }
}
