<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Support\Str;

/** How a message looks to the web and the app (B6.1); the same shape live and when loaded. */
final class MessagePresenter
{
    /**
     * @return array{id: string, body: string|null, sender: array{id: int, name: string}|null, reply_to: array{id: string, body: string|null, sender: string|null, deleted: bool}|null, edited: bool, deleted: bool, created_at: string, changeable_until: string|null, attachments: list<array{id: string, name: string, mime: string, size: int, image: bool, status: string, url: string|null}>}
     */
    public static function message(Message $message): array
    {
        $reply = $message->replyTo;
        $until = $message->created_at->copy()->addMinutes(Message::CHANGE_MINUTES);

        return [
            'id' => $message->uuid,
            'body' => $message->deleted_at === null ? $message->body : null,
            // Null once the sender's account is gone ("Former member").
            'sender' => $message->sender === null ? null : ['id' => $message->sender->id, 'name' => $message->sender->name ?? $message->sender->email ?? ''],
            'reply_to' => $reply === null ? null : [
                'id' => $reply->uuid,
                'body' => $reply->deleted_at === null && $reply->body !== null ? Str::limit($reply->body, 120) : null,
                'sender' => $reply->sender?->name,
                'deleted' => $reply->deleted_at !== null,
            ],
            'edited' => $message->edited_at !== null,
            'deleted' => $message->deleted_at !== null,
            'created_at' => $message->created_at->toIso8601String(),
            'changeable_until' => $message->deleted_at === null && $until->isFuture() ? $until->toIso8601String() : null,
            // Files open once scanned (B6.1b); an infected one shows as removed.
            'attachments' => $message->deleted_at !== null ? [] : array_values($message->attachments->map(static fn (MessageAttachment $a): array => [
                'id' => $a->uuid,
                'name' => $a->name,
                'mime' => $a->mime,
                'size' => $a->size,
                'image' => $a->isImage(),
                'status' => $a->scan_status,
                'url' => Attachments::url($a),
            ])->all()),
        ];
    }
}
