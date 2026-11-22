<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationSettingsRequest;
use App\Mail\Ses\EmailSuppressions;
use App\Models\User;
use App\Notifications\Centre\Inbox;
use App\Notifications\Centre\Preferences;
use App\Support\PersonTimezone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notification centre on the web (B6.2): the inbox behind the bell in
 * the top bar, and Settings › Notifications (each kind by channel, quiet
 * hours, digests).
 */
final class NotificationsController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('notifications/index', Inbox::for($user));
    }

    public function read(string $notification, #[CurrentUser] User $user): RedirectResponse
    {
        Inbox::read($user, $notification);

        return back();
    }

    public function readAll(#[CurrentUser] User $user): RedirectResponse
    {
        Inbox::read($user);

        return back();
    }

    public function settings(Request $request, #[CurrentUser] User $user, Preferences $preferences): Response
    {
        return Inertia::render('settings/notifications', [
            ...$preferences->summary($user),
            // The browser's zone, offered when it differs from the saved one.
            'browserTimezone' => PersonTimezone::of($request),
        ]);
    }

    /** Email stopped after a bounce or complaint; the person fixed it (B6.2d). */
    public function resumeEmail(#[CurrentUser] User $user, EmailSuppressions $suppressions): RedirectResponse
    {
        $suppressions->resume($user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('notifications.email.resumed')]);

        return back();
    }

    public function update(NotificationSettingsRequest $request, #[CurrentUser] User $user, Preferences $preferences): RedirectResponse
    {
        $preferences->save($user, $request->changes());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('notifications.saved')]);

        return back();
    }
}
