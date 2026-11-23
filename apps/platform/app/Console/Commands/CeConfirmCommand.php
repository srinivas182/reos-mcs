<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Licences\Ce\CeConfirmations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Nightly after TREC's file: CE confirmed on TREC's record, and renewals seen (A11.2). */
#[Signature('reos:ce:confirm')]
#[Description("Mark CE on TREC's record and confirm renewals from the nightly TREC file (A11.2)")]
final class CeConfirmCommand extends Command
{
    public function handle(CeConfirmations $confirmations): int
    {
        $done = $confirmations->run();
        $this->info("Confirmed {$done['confirmed']} completions and {$done['renewed']} renewals.");

        return self::SUCCESS;
    }
}
