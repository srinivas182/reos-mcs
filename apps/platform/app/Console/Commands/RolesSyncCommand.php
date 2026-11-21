<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Authorization\RoleCatalogue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Writes the roles and permissions defined in code to the database (run on deploy). */
#[Signature('reos:roles:sync')]
#[Description('Sync REOS roles and permissions to the database')]
final class RolesSyncCommand extends Command
{
    public function handle(RoleCatalogue $catalogue): int
    {
        $catalogue->sync();
        $this->components->info('Roles and permissions are up to date.');

        return self::SUCCESS;
    }
}
