<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Brokerage\ApprovalFallbacks;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reos:approvals:fallback')]
#[Description('Pass approvals and questions waiting past the working-hours limit to the office admins (A15.3)')]
final class ApprovalsFallbackCommand extends Command
{
    public function handle(ApprovalFallbacks $fallbacks): int
    {
        $this->components->info($fallbacks->run().' items passed to office admins.');

        return self::SUCCESS;
    }
}
