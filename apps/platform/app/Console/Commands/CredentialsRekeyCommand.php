<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Integrations\Credentials\CredentialVault;
use App\Integrations\Credentials\DatabaseCredentialVault;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Re-encrypts every saved integration key with the current key, after rotation. */
#[Signature('reos:credentials:rekey')]
#[Description('Re-encrypt saved integration keys with the current key')]
final class CredentialsRekeyCommand extends Command
{
    public function handle(CredentialVault $vault): int
    {
        if (! $vault instanceof DatabaseCredentialVault) {
            $this->components->info('Keys are not stored in the database; nothing to re-encrypt.');

            return self::SUCCESS;
        }

        $count = $vault->reencrypt();
        $this->components->info("Re-encrypted {$count} saved key set(s).");

        return self::SUCCESS;
    }
}
