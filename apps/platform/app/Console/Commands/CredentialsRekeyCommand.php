<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Integrations\Credentials\CredentialVault;
use App\Integrations\Credentials\DatabaseCredentialVault;
use App\Models\Connection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Re-encrypts every saved integration key, and every connection's tokens
 * (B3.1), with the current key after rotation.
 */
#[Signature('reos:credentials:rekey')]
#[Description('Re-encrypt saved integration keys with the current key')]
final class CredentialsRekeyCommand extends Command
{
    public function handle(CredentialVault $vault): int
    {
        if ($vault instanceof DatabaseCredentialVault) {
            $count = $vault->reencrypt();
            $this->components->info("Re-encrypted {$count} saved key set(s).");
        } else {
            $this->components->info('Keys are not stored in the database; nothing to re-encrypt.');
        }

        // Reading decrypts with any listed key; saving encrypts with the current one.
        $tokens = 0;
        Connection::query()->orderBy('id')->chunkById(200, function ($connections) use (&$tokens): void {
            foreach ($connections as $connection) {
                $connection->forceFill([
                    'access_token' => $connection->access_token,
                    'refresh_token' => $connection->refresh_token,
                ])->save();
                $tokens++;
            }
        });
        $this->components->info("Re-encrypted the tokens of {$tokens} connection(s).");

        return self::SUCCESS;
    }
}
