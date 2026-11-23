<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncMlsFeed;
use App\Mls\MlsFeeds;
use App\Mls\MlsSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Every 15 minutes (B10.1): queue a sync of each MLS feed; `--now` runs them here. */
#[Signature('reos:mls:sync {--now : Sync here instead of queueing} {--mls= : Only this register id}')]
#[Description('Replicate MLS listings, members, offices and open houses from each RESO feed')]
final class MlsSyncCommand extends Command
{
    public function handle(MlsFeeds $feeds, MlsSync $sync): int
    {
        $only = $this->option('mls');

        foreach ($feeds->all() as $definition) {
            if (is_string($only) && $only !== $definition->id) {
                continue;
            }

            if ($this->option('now') === true) {
                $result = $sync->run($definition);
                $this->components->info("{$definition->id}: ".($result['source'] ?? 'off').", {$result['saved']} saved, {$result['removed']} removed.");
            } else {
                SyncMlsFeed::dispatch($definition->id);
            }
        }

        return self::SUCCESS;
    }
}
