<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\AnnouncementRequest;
use App\Messaging\Announcements;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Announcements (B6.1b; association admin's Announcements, and the
 * brokerage and school admin screens): what the organisation has sent with
 * "Read by 82 of 140", and New announcement to everyone or a group.
 */
final class AnnouncementController extends Controller
{
    public function index(#[CurrentUser] User $user, Announcements $announcements): Response
    {
        $sender = $announcements->sender($user);
        abort_if($sender === null, 403);

        return Inertia::render('announcements/index', [
            'organisation' => $sender->organisation->name,
            'sent' => $announcements->sent($sender->organisation),
            'options' => $announcements->audienceOptions($sender->organisation),
        ]);
    }

    public function store(AnnouncementRequest $request, #[CurrentUser] User $user, Announcements $announcements): RedirectResponse
    {
        $announcement = $announcements->send($user, $request->string('title')->toString(), $request->string('body')->toString(), $request->audience());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('announcements.sent_'.($announcement->recipients_count === 1 ? 'one' : 'other'), ['count' => (string) $announcement->recipients_count])]);

        return back();
    }

    /** Read on the web (the Messages page shows unread ones). */
    public function read(string $announcement, #[CurrentUser] User $user, Announcements $announcements): JsonResponse
    {
        $announcements->read($user, $announcement);

        return response()->json(['read' => true]);
    }
}
