<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the notification centre's working tables small (B6.2, data map):
 * the delivery log for 90 days, held alerts and digest items for a week
 * after they went out.
 */
#[Signature('reos:notifications:prune')]
#[Description('Delete old notification delivery logs, released alerts and sent digest items')]
final class PruneNotificationsCommand extends Command
{
    public const LOG_DAYS = 90;

    public function handle(): int
    {
        $log = DB::table('notification_deliveries')->where('created_at', '<', now()->subDays(self::LOG_DAYS))->delete();
        $held = DB::table('held_notifications')->whereNotNull('released_at')->where('released_at', '<', now()->subWeek())->delete();
        $digest = DB::table('digest_items')->whereNotNull('sent_at')->where('sent_at', '<', now()->subWeek())->delete();

        $this->components->info("{$log} log rows, {$held} released alerts and {$digest} digest items deleted.");

        return self::SUCCESS;
    }
}
