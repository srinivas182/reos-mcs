<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Messaging\Announcements;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AnnouncementController extends Controller
{
    /**
     * My announcements (B6.1b, Today).
     *
     * From each brokerage, school or association I belong to, newest first
     * (the latest 20), with whether I've read each. New ones arrive live on
     * `users.{id}` (`announcement.posted`).
     *
     * @response array{unread: int, announcements: list<array{id: string, title: string, body: string, from: string, author: string|null, sent_at: string, read: bool}>}
     */
    public function index(#[CurrentUser] User $user, Announcements $announcements): JsonResponse
    {
        $mine = $announcements->mine($user);

        return response()->json(['unread' => count(array_filter($mine, static fn (array $a): bool => ! $a['read'])), 'announcements' => $mine]);
    }

    /**
     * Mark an announcement read (B6.1b): it counts toward "Read by".
     *
     * @response array{read: bool}
     */
    public function read(string $announcement, #[CurrentUser] User $user, Announcements $announcements): JsonResponse
    {
        $announcements->read($user, $announcement);

        return response()->json(['read' => true]);
    }

    /**
     * Who has read an announcement (A16.3), for the admin who sent it.
     *
     * Unread people first, then by name. `can_remind` is false once everyone
     * has read it, or for a day after the last reminder.
     *
     * @response array{id: string, title: string, recipients: int, read: int, reminded_at: string|null, can_remind: bool, people: list<array{name: string, read_at: string|null}>}
     */
    public function readers(string $announcement, #[CurrentUser] User $user, Announcements $announcements): JsonResponse
    {
        return response()->json($announcements->readers($user, $announcement));
    }

    /**
     * Remind the people who haven't read it (A16.3), once a day at most.
     *
     * @response array{reminded: int}
     */
    public function remind(string $announcement, #[CurrentUser] User $user, Announcements $announcements): JsonResponse
    {
        return response()->json(['reminded' => $announcements->remindUnread($user, $announcement)]);
    }
}
