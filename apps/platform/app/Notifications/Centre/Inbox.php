<?php

declare(strict_types=1);

namespace App\Notifications\Centre;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/** The in-app inbox (B6.2): the bell on the web and the phone. */
final class Inbox
{
    public const PAGE = 30;

    /**
     * @return array{unread: int, items: list<array{id: string, kind: string, title: string, body: string|null, url: string|null, read: bool, at: string}>}
     */
    public static function for(User $user): array
    {
        $items = $user->notifications()->latest()->limit(self::PAGE)->get();

        return [
            'unread' => $user->unreadNotifications()->count(),
            'items' => array_values($items->map(static function (DatabaseNotification $n): array {
                $data = $n->data;

                return [
                    'id' => $n->id,
                    'kind' => is_string($data['kind'] ?? null) ? $data['kind'] : 'other',
                    'title' => is_string($data['title'] ?? null) ? $data['title'] : '',
                    'body' => is_string($data['body'] ?? null) ? $data['body'] : null,
                    'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
                    'read' => $n->read_at !== null,
                    'at' => $n->created_at?->toIso8601String() ?? '',
                ];
            })->all()),
        ];
    }

    public static function read(User $user, ?string $id = null): int
    {
        $query = $user->unreadNotifications();

        if ($id !== null) {
            $query->whereKey($id);
        }

        $query->update(['read_at' => now()]);

        return $user->unreadNotifications()->count();
    }
}
