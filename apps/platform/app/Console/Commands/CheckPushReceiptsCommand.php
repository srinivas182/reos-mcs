<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\Centre\Push\PushSenderFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Checks what happened to pushes sent at least 15 minutes ago (B6.2b): a
 * phone Expo reports as DeviceNotRegistered (the app was deleted or
 * notifications turned off) is removed so REOS stops sending to it. Expo
 * keeps receipts for a day, so older tickets are dropped unchecked.
 */
#[Signature('reos:push:receipts')]
#[Description('Remove phones that Expo push receipts report as gone')]
final class CheckPushReceiptsCommand extends Command
{
    /** Expo answers up to 1,000 receipt ids per request. */
    private const BATCH = 1000;

    public function handle(PushSenderFactory $senders): int
    {
        DB::table('push_tickets')->where('created_at', '<', now()->subDay())->delete();
        $sender = $senders->make();

        if ($sender === null) {
            return self::SUCCESS;
        }

        $removed = 0;

        DB::table('push_tickets')->where('created_at', '<=', now()->subMinutes(15))->orderBy('id')
            ->chunkById(self::BATCH, function ($rows) use ($sender, &$removed): void {
                /** @var array<string, int> $devices ticket => push device */
                $devices = [];

                foreach ($rows as $row) {
                    if (is_string($row->ticket) && is_numeric($row->push_device_id)) {
                        $devices[$row->ticket] = (int) $row->push_device_id;
                    }
                }

                $receipts = $sender->receipts(array_keys($devices));
                $gone = [];

                foreach ($receipts as $ticket => $error) {
                    if ($error === 'DeviceNotRegistered' && isset($devices[$ticket])) {
                        $gone[] = $devices[$ticket];
                    }
                }

                if ($gone !== []) {
                    $removed += DB::table('push_devices')->whereIn('id', array_unique($gone))->delete();
                }

                // Answered tickets are done; ones without a receipt yet wait for the next run.
                DB::table('push_tickets')->whereIn('ticket', array_keys($receipts))->delete();
            });

        $this->info("Removed {$removed} phone(s) that no longer get push.");

        return self::SUCCESS;
    }
}
