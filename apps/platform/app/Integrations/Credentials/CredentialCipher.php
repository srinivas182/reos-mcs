<?php

declare(strict_types=1);

namespace App\Integrations\Credentials;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Encrypts integration keys with their own key (REOS_CREDENTIALS_KEY), not the
 * app key, so a leaked APP_KEY or database copy alone can't reveal vendor keys.
 *
 * Rotation: set a new key, keep the old one in REOS_CREDENTIALS_PREVIOUS_KEYS,
 * run `php artisan reos:credentials:rekey`, then drop the old key.
 */
final class CredentialCipher
{
    public const CIPHER = 'aes-256-gcm';

    private ?Encrypter $encrypter = null;

    /**
     * @param  list<string>  $previousKeys
     */
    public function __construct(
        private readonly ?string $key,
        private readonly array $previousKeys = [],
    ) {}

    /** A new random key in the `base64:` form used in .env files. */
    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey(self::CIPHER));
    }

    public function encrypt(string $plaintext): string
    {
        return $this->encrypter()->encryptString($plaintext);
    }

    public function decrypt(string $payload): string
    {
        return $this->encrypter()->decryptString($payload);
    }

    private function encrypter(): Encrypter
    {
        if ($this->encrypter !== null) {
            return $this->encrypter;
        }

        if ($this->key === null || $this->key === '') {
            throw new RuntimeException('REOS_CREDENTIALS_KEY is not set. Run `php artisan reos:credentials:key` (see docs/integrations/credentials-policy.md).');
        }

        return $this->encrypter = (new Encrypter(self::parse($this->key), self::CIPHER))
            ->previousKeys(array_map(self::parse(...), $this->previousKeys));
    }

    private static function parse(string $key): string
    {
        $raw = Str::startsWith($key, 'base64:') ? base64_decode(Str::after($key, 'base64:'), true) : $key;

        if ($raw === false || ! Encrypter::supported($raw, self::CIPHER)) {
            throw new RuntimeException('REOS_CREDENTIALS_KEY must be a 32-byte key. Run `php artisan reos:credentials:key`.');
        }

        return $raw;
    }
}
