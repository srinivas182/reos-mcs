<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationSettingsRequest;
use App\Mail\Ses\EmailSuppressions;
use App\Models\User;
use App\Notifications\Centre\Inbox;
use App\Notifications\Centre\Preferences;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class NotificationsController extends Controller
{
    /**
     * My notifications (B6.2, the bell).
     *
     * The latest 30, newest first, and how many are unread.
     *
     * @response array{unread: int, items: list<array{id: string, kind: string, title: string, body: string|null, url: string|null, read: bool, at: string}>}
     */
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(Inbox::for($user));
    }

    /**
     * Mark one read (B6.2).
     *
     * @response array{unread: int}
     */
    public function read(string $notification, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['unread' => Inbox::read($user, $notification)]);
    }

    /**
     * Resume email after a bounce or complaint (B6.2d).
     *
     * For people who fixed their mailbox. If it bounces again, it stops again.
     *
     * @response array{address: string|null, stopped: array{reason: 'bounce'|'complaint', at: string}|null}
     */
    public function resumeEmail(#[CurrentUser] User $user, EmailSuppressions $suppressions): JsonResponse
    {
        $suppressions->resume($user);

        return response()->json(['address' => $user->email, 'stopped' => $suppressions->for($user)]);
    }

    /**
     * Mark all read (B6.2).
     *
     * @response array{unread: int}
     */
    public function readAll(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['unread' => Inbox::read($user)]);
    }

    /**
     * My notification settings (B6.2, Me › Notifications).
     *
     * Each kind with its channels (on or off), whether it's locked (security
     * messages), urgent (arrives in quiet hours) or can be a digest; quiet
     * hours and the time zone they follow.
     *
     * @response array{kinds: list<array{kind: string, group: string, locked: bool, urgent: bool, digestible: bool, channels: array{inbox: bool, push: bool, email: bool, sms: bool}, digest: 'daily'|'weekly'|null}>, channels: list<'inbox'|'push'|'email'|'sms'>, phones: int, texts: array{phone: string|null, on: bool, available: bool}, email: array{address: string|null, stopped: array{reason: 'bounce'|'complaint', at: string}|null}, quiet: array{enabled: bool, start: string, end: string}, timezone: string}
     */
    public function settings(#[CurrentUser] User $user, Preferences $preferences): JsonResponse
    {
        return response()->json($preferences->summary($user));
    }

    /**
     * Change my notification settings (B6.2).
     *
     * Send only what changes: `channels.{kind}.{channel}` true or false,
     * `digests.{kind}` daily, weekly or null, `quiet` (enabled, start, end as
     * HH:MM) and `timezone`. Locked kinds can't be turned off (422).
     *
     * @response array{kinds: list<array{kind: string, group: string, locked: bool, urgent: bool, digestible: bool, channels: array{inbox: bool, push: bool, email: bool, sms: bool}, digest: 'daily'|'weekly'|null}>, channels: list<'inbox'|'push'|'email'|'sms'>, phones: int, texts: array{phone: string|null, on: bool, available: bool}, email: array{address: string|null, stopped: array{reason: 'bounce'|'complaint', at: string}|null}, quiet: array{enabled: bool, start: string, end: string}, timezone: string}
     */
    public function update(NotificationSettingsRequest $request, #[CurrentUser] User $user, Preferences $preferences): JsonResponse
    {
        $preferences->save($user, $request->changes());

        return response()->json($preferences->summary($user));
    }
}
