<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Studio\Celebrations\Celebrations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Every hour: milestones made into free posts, and the day's one shown (B12.3). */
#[Signature('reos:celebrations')]
#[Description('Make milestones into celebration posts and show at most one a day (B12.3)')]
final class CelebrationsCommand extends Command
{
    public function handle(Celebrations $celebrations): int
    {
        $this->info('Showed '.$celebrations->run().' celebrations.');

        return self::SUCCESS;
    }
}
