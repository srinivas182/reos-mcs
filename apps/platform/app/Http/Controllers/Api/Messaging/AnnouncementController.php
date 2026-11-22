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
}
