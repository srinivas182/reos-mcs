<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Integrations\Credentials\CredentialCipher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Creates the key that encrypts integration keys (REOS_CREDENTIALS_KEY).
 *
 * Locally it writes the key to .env. On staging and production, use --show
 * and put the value in AWS Secrets Manager (credentials policy).
 */
#[Signature('reos:credentials:key {--show : Print the key instead of writing .env} {--rotate : Replace the key, keeping the old one as a previous key}')]
#[Description('Create the key that encrypts integration keys')]
final class CredentialsKeyCommand extends Command
{
    public function handle(): int
    {
        $key = CredentialCipher::generateKey();

        if ($this->option('show')) {
            $this->line($key);

            return self::SUCCESS;
        }

        $path = $this->laravel->environmentFilePath();
        $contents = is_file($path) ? (string) file_get_contents($path) : '';
        $current = $this->current($contents, 'REOS_CREDENTIALS_KEY');

        if ($current !== null && $current !== '' && ! $this->option('rotate')) {
            $this->components->info('REOS_CREDENTIALS_KEY is already set. Use --rotate to replace it.');

            return self::SUCCESS;
        }

        $contents = $this->set($contents, 'REOS_CREDENTIALS_KEY', $key);

        if ($current !== null && $current !== '') {
            $previous = array_filter([$current, $this->current($contents, 'REOS_CREDENTIALS_PREVIOUS_KEYS')]);
            $contents = $this->set($contents, 'REOS_CREDENTIALS_PREVIOUS_KEYS', implode(',', $previous));
        }

        file_put_contents($path, $contents);

        $this->components->info($current ? 'New key set. Run `php artisan reos:credentials:rekey`, then remove REOS_CREDENTIALS_PREVIOUS_KEYS.' : 'REOS_CREDENTIALS_KEY set.');

        return self::SUCCESS;
    }

    private function current(string $contents, string $name): ?string
    {
        return preg_match('/^'.$name.'=(.*)$/m', $contents, $match) === 1 ? trim($match[1]) : null;
    }

    private function set(string $contents, string $name, string $value): string
    {
        $line = "{$name}={$value}";

        if (preg_match('/^'.$name.'=.*$/m', $contents) === 1) {
            return (string) preg_replace('/^'.$name.'=.*$/m', $line, $contents);
        }

        return rtrim($contents, "\n")."\n{$line}\n";
    }
}
