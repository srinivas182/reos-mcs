<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TextNumberRequest;
use App\Http\Requests\Settings\TextSwitchRequest;
use App\Models\User;
use App\Notifications\Centre\Sms\TextAlerts;
use App\Privacy\ConsentMethod;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Settings › Notifications › Texts (B6.2c): the mobile number and the texts switch. */
final class TextsController extends Controller
{
    public function sendCode(TextNumberRequest $request, #[CurrentUser] User $user, TextAlerts $texts): RedirectResponse
    {
        $texts->startNumber($user, $request->phone(), $user->preferredLocale());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('notifications.texts.codeSent')]);

        return back();
    }

    public function verify(TextNumberRequest $request, #[CurrentUser] User $user, TextAlerts $texts): RedirectResponse
    {
        $texts->verifyNumber($user, $request->phone(), $request->code());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('notifications.texts.verified')]);

        return back();
    }

    public function remove(#[CurrentUser] User $user, TextAlerts $texts): RedirectResponse
    {
        $texts->removeNumber($user);

        return back();
    }

    public function update(TextSwitchRequest $request, #[CurrentUser] User $user, TextAlerts $texts): RedirectResponse
    {
        $texts->turn($user, $request->on(), ConsentMethod::Web);
        Inertia::flash('toast', ['type' => 'success', 'message' => __($request->on() ? 'notifications.texts.turnedOn' : 'notifications.texts.turnedOff')]);

        return back();
    }
}
