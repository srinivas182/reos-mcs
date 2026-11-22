<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Content\Video\MuxWebhooks;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `POST /webhooks/mux`: Mux says a lesson video or its captions are ready (A5.3). Signed; each event handled once. */
final class MuxWebhookController extends Controller
{
    public function __invoke(Request $request, MuxWebhooks $webhooks): JsonResponse
    {
        return match ($webhooks->receive($request->getContent(), $request->header('Mux-Signature'))) {
            MuxWebhooks::ACCEPTED => response()->json(['received' => true]),
            MuxWebhooks::DUPLICATE => response()->json(['received' => true, 'duplicate' => true]),
            default => response()->json(['received' => false], 401),
        };
    }
}
