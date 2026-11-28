<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Accounting\QuickBooksExports;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Daily (B17.3): connected brokerages post signed split sheets and payouts to QuickBooks, and failures are tried again. */
#[Signature('reos:accounting:export')]
#[Description('Post signed split sheets and payouts to QuickBooks Online (B17.3)')]
final class ExportToQuickBooks extends Command
{
    public function handle(QuickBooksExports $exports): int
    {
        $this->info('Posted '.$exports->sweep().' journal entries.');

        return self::SUCCESS;
    }
}
