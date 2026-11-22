<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Mail\Ses\SesNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `POST /webhooks/ses`: Amazon SNS with SES bounces and complaints (B6.2d). Signed by SNS. */
final class SesWebhookController extends Controller
{
    public function __invoke(Request $request, SesNotifications $notifications): JsonResponse
    {
        return $notifications->receive($request->getContent()) === SesNotifications::ACCEPTED
            ? response()->json(['received' => true])
            : response()->json(['received' => false], 403);
    }
}
