<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\Centre\CentreNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Sends the alerts held during quiet hours once they've ended (B6.2). */
#[Signature('reos:notifications:release')]
#[Description('Send alerts held during quiet hours')]
final class ReleaseHeldNotificationsCommand extends Command
{
    public function handle(): int
    {
        $sent = 0;

        DB::table('held_notifications')->whereNull('released_at')->where('release_at', '<=', now())->orderBy('id')->lazyById(200)
            ->each(function (object $row) use (&$sent): void {
                // Claim it first so two runs can't send it twice.
                if (DB::table('held_notifications')->where('id', $row->id)->whereNull('released_at')->update(['released_at' => now()]) === 0) {
                    return;
                }

                $user = User::query()->find($row->user_id);
                $channels = json_decode(is_string($row->channels) ? $row->channels : '[]', true);

                try {
                    $notification = unserialize(is_string($row->notification) ? $row->notification : '', ['allowed_classes' => true]);
                } catch (Throwable) {
                    return;
                }

                if ($user === null || ! $notification instanceof CentreNotification || ! is_array($channels) || $channels === []) {
                    return;
                }

                $notification->released = true;
                Notification::sendNow($user, $notification, array_values(array_filter($channels, is_string(...))));
                $sent++;
            });

        $this->info("Released {$sent} held notification(s).");

        return self::SUCCESS;
    }
}
