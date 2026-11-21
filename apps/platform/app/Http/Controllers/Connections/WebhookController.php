<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connections;

use App\Http\Controllers\Controller;
use App\Services\Connections\WebhookInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `POST /webhooks/{integration}`: tools tell REOS something changed. Signed,
 * de-duplicated and handled on the queue (ADR 0013). Tools that check the
 * address first (Microsoft Graph) get their token back (ADR 0017).
 */
final class WebhookController extends Controller
{
    public function __invoke(Request $request, string $tool, WebhookInbox $inbox): JsonResponse|Response
    {
        // Microsoft Graph checks the address first: echo its token, as plain text.
        $answer = $inbox->validation($tool, $request);

        if ($answer !== null) {
            return response($answer, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'X-Content-Type-Options' => 'nosniff']);
        }

        return match ($inbox->receive($tool, $request)) {
            WebhookInbox::ACCEPTED => response()->json(['received' => true], 202),
            WebhookInbox::DUPLICATE => response()->json(['received' => true, 'duplicate' => true]),
            default => response()->json(['received' => false], 401),
        };
    }
}
