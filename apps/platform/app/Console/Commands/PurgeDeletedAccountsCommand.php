<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Identity\AccountDeletion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Runs daily (routes/console.php). */
#[Signature('reos:accounts:purge')]
#[Description('Delete accounts whose 30-day deletion grace period has ended')]
final class PurgeDeletedAccountsCommand extends Command
{
    public function handle(AccountDeletion $deletion): int
    {
        $this->components->info("Deleted {$deletion->purge()} account(s).");

        return self::SUCCESS;
    }
}
