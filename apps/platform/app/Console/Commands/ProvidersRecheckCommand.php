<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Licences\ProviderLicences;
use App\Models\ProviderLicence;
use App\Models\ProviderProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reos:providers:recheck')]
#[Description('Re-check live and hidden providers\' licences against the regulators\' latest data (B15.2)')]
final class ProvidersRecheckCommand extends Command
{
    public function handle(ProviderLicences $licences): int
    {
        $users = ProviderProfile::query()->whereIn('status', [ProviderProfile::LIVE, ProviderProfile::HIDDEN])->pluck('user_id');
        $checked = 0;

        ProviderLicence::query()->with('user')->whereIn('user_id', $users)->whereIn('kind', ['trec_inspector', 'tdi'])->chunkById(200, function ($rows) use ($licences, &$checked): void {
            foreach ($rows as $licence) {
                $licences->recheck($licence);
                $checked++;
            }
        });

        $this->components->info("{$checked} provider licences re-checked.");

        return self::SUCCESS;
    }
}
