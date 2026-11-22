<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\Ledger\Reconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reos:ledger:reconcile {--day= : The day to check (YYYY-MM-DD); yesterday by default}')]
#[Description('Match yesterday\'s ledger money entries with each gateway (B5.3)')]
final class LedgerReconcileCommand extends Command
{
    public function handle(Reconciliation $reconciliation): int
    {
        $option = $this->option('day');
        $day = is_string($option) && $option !== '' ? CarbonImmutable::parse($option) : CarbonImmutable::yesterday();

        foreach ($reconciliation->run($day) as $run) {
            $this->line(sprintf('%s %s: %s, %d matched, %d differences', $run->gateway, $run->environment, $run->status, $run->matched, count($run->differences ?? [])));
        }

        return self::SUCCESS;
    }
}
