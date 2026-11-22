<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TextNumberRequest;
use App\Http\Requests\Settings\TextSwitchRequest;
use App\Models\User;
use App\Notifications\Centre\Sms\TextAlerts;
use App\Privacy\ConsentMethod;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class TextsController extends Controller
{
    /**
     * Send a code to a mobile number for texts (B6.2c).
     *
     * US numbers only. The number replaces the current one once verified.
     *
     * @response array{phone: string|null, on: bool, available: bool}
     */
    public function sendCode(TextNumberRequest $request, #[CurrentUser] User $user, TextAlerts $texts): JsonResponse
    {
        $texts->startNumber($user, $request->phone(), $user->preferredLocale());

        return response()->json($texts->status($user));
    }

    /**
     * Verify the mobile number for texts (B6.2c).
     *
     * @response array{phone: string|null, on: bool, available: bool}
     */
    public function verify(TextNumberRequest $request, #[CurrentUser] User $user, TextAlerts $texts): JsonResponse
    {
        $texts->verifyNumber($user, $request->phone(), $request->code());

        return response()->json($texts->status($user->refresh()));
    }

    /**
     * Turn texts on or off (B6.2c).
     *
     * Turning them on records the person's SMS consent and sends the
     * confirmation text; it needs a verified number first. Then choose which
     * kinds come by text in the notification settings.
     *
     * @response array{phone: string|null, on: bool, available: bool}
     */
    public function update(TextSwitchRequest $request, #[CurrentUser] User $user, TextAlerts $texts): JsonResponse
    {
        $texts->turn($user, $request->on(), ConsentMethod::App);

        return response()->json($texts->status($user));
    }
}
