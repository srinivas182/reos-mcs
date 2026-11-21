<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connections;

use App\Http\Controllers\Controller;
use App\Services\Connections\WebhookInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /webhooks/{integration}`: tools tell REOS something changed. Signed,
 * de-duplicated and handled on the queue (ADR 0013).
 */
final class WebhookController extends Controller
{
    public function __invoke(Request $request, string $tool, WebhookInbox $inbox): JsonResponse
    {
        return match ($inbox->receive($tool, $request)) {
            WebhookInbox::ACCEPTED => response()->json(['received' => true], 202),
            WebhookInbox::DUPLICATE => response()->json(['received' => true, 'duplicate' => true]),
            default => response()->json(['received' => false], 401),
        };
    }
}
