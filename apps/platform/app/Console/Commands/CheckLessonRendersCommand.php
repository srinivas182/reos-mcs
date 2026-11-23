<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Ai\LessonPipeline;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reos:lessons:renders')]
#[Description('Checks AI lesson videos still rendering at HeyGen or Synthesia, and keeps the finished ones (A7.3).')]
final class CheckLessonRendersCommand extends Command
{
    public function handle(LessonPipeline $pipeline): int
    {
        $this->info($pipeline->checkRenders().' finished.');

        return self::SUCCESS;
    }
}
