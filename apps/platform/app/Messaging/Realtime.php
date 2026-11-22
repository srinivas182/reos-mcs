<?php

declare(strict_types=1);

namespace App\Messaging;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends live updates through Reverb (B6.1). A message is saved first; if
 * the WebSocket server is unreachable the update is skipped (the screens
 * catch up when they next load), never the message.
 */
final class Realtime
{
    public static function send(ShouldBroadcastNow $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('Live update not sent; the message is saved.', ['event' => $event::class, 'error' => $e::class]);
        }
    }
}
