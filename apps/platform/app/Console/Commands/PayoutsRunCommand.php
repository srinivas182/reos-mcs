<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\Payouts\PayoutRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reos:payouts:run')]
#[Description('Pay schools, instructors and mentors what they earned (weekly, B5.2b)')]
final class PayoutsRunCommand extends Command
{
    public function handle(PayoutRun $run): int
    {
        $result = $run->run();
        $this->info(sprintf('%d payouts (%s USD); %d payees still setting up.', $result['payouts'], number_format($result['amount'] / 100, 2), $result['waiting']));

        return self::SUCCESS;
    }
}
