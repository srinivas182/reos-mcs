<?php

declare(strict_types=1);

namespace App\Notifications\Centre\Push;

/**
 * Sends push notifications to phones (B6.2b): Expo's push service, or the
 * simulator outside production until Expo push is set up.
 *
 * @phpstan-type PushMessage array{to: string, title: string, body?: string, data: array<string, mixed>, sound?: string, badge?: int, priority?: string, channelId?: string, collapseId?: string, threadId?: string, ttl?: int}
 * @phpstan-type PushTicket array{status: string, id?: string, error?: string}
 */
interface PushSender
{
    /**
     * Sends up to 100 messages; one ticket back for each, in order.
     *
     * @param  list<PushMessage>  $messages
     * @return list<PushTicket>
     */
    public function send(array $messages): array;

    /**
     * What happened to sent messages: receipt id => error code, or null when
     * delivered to Apple or Google. Ids without a receipt yet are left out.
     *
     * @param  list<string>  $tickets
     * @return array<string, string|null>
     */
    public function receipts(array $tickets): array;
}
