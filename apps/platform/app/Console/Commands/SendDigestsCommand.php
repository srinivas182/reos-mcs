<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationSetting;
use App\Models\User;
use App\Notifications\Digest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sends digests at 8 AM in each person's time zone (B6.2): daily ones every
 * day, weekly ones on Mondays. Runs hourly; a digest goes once.
 */
#[Signature('reos:notifications:digests')]
#[Description('Send daily and weekly notification digests at 8 AM local time')]
final class SendDigestsCommand extends Command
{
    public const HOUR = 8;

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $sent = 0;

        $users = DB::table('digest_items')->whereNull('sent_at')->distinct()->pluck('user_id');

        foreach ($users as $userId) {
            $user = is_numeric($userId) ? User::query()->find((int) $userId) : null;

            if ($user === null) {
                continue;
            }

            $zone = NotificationSetting::query()->whereKey($user->id)->value('timezone');
            $local = $now->setTimezone(is_string($zone) ? $zone : 'America/Chicago');

            if ($local->hour !== self::HOUR) {
                continue;
            }

            foreach ($local->isMonday() ? ['daily', 'weekly'] : ['daily'] as $frequency) {
                $items = DB::table('digest_items')->where('user_id', $user->id)->where('frequency', $frequency)->whereNull('sent_at')->orderBy('id')->get();

                if ($items->isEmpty()) {
                    continue;
                }

                DB::table('digest_items')->whereIn('id', $items->pluck('id'))->update(['sent_at' => $now]);
                $user->notify(new Digest($frequency, array_values($items->map(static fn (object $i): array => [
                    'title' => is_string($i->title) ? $i->title : '',
                    'body' => is_string($i->body) ? $i->body : null,
                    'url' => is_string($i->url) ? $i->url : null,
                ])->all())));
                $sent++;
            }
        }

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }
}
