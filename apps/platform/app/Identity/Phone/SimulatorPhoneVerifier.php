<?php

declare(strict_types=1);

namespace App\Identity\Phone;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Hash;
use Psr\Log\LoggerInterface;

/**
 * Demo codes (Twilio Verify on demo data, never in production): the code is
 * written to the log instead of a text message, so phone sign-in can be
 * tried on development and staging before Twilio keys exist.
 */
final readonly class SimulatorPhoneVerifier implements PhoneVerifier
{
    public const TTL_SECONDS = 600;

    public function __construct(private Cache $cache, private LoggerInterface $log) {}

    public function send(string $phone, string $locale): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $this->cache->put($this->key($phone), Hash::make($code), self::TTL_SECONDS);

        $this->log->info("[REOS demo] Sign-in code for {$phone}: {$code}");
    }

    public function check(string $phone, string $code): bool
    {
        $hash = $this->cache->get($this->key($phone));

        if (! is_string($hash) || ! Hash::check($code, $hash)) {
            return false;
        }

        $this->cache->forget($this->key($phone));

        return true;
    }

    private function key(string $phone): string
    {
        return 'reos:phone-code:'.hash('sha256', $phone);
    }
}
