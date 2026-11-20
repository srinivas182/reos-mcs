<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Identity\Phone\PhoneVerifierFactory;
use App\Identity\SignInFailed;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Phone sign-in: send a code, then check it. Five wrong codes lock that
 * number for 15 minutes (journey screen "Phone code").
 */
final readonly class PhoneSignIn
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public function __construct(
        private PhoneVerifierFactory $verifiers,
        private AccountResolver $accounts,
        private AuditLogger $audit,
        private Cache $cache,
    ) {}

    public function start(string $phone, string $locale): void
    {
        $this->ensureNotLocked($phone);
        $this->verifiers->make()->send($phone, $locale);
    }

    /**
     * @return array{user: User, created: bool}
     */
    public function verify(string $phone, string $code): array
    {
        $this->ensureNotLocked($phone);

        if (! $this->verifiers->make()->check($phone, $code)) {
            $attempts = $this->attempts($phone) + 1;
            $this->cache->put($this->key($phone), $attempts, now()->addMinutes(self::LOCK_MINUTES));

            $this->audit->record('auth.sign_in_failed', 'phone', $this->mask($phone), [
                'method' => 'phone',
                'attempt' => $attempts,
            ]);

            throw new SignInFailed('identity.errors.wrongCode', 'Wrong or expired code');
        }

        $this->cache->forget($this->key($phone));

        return $this->accounts->fromPhone($phone);
    }

    private function ensureNotLocked(string $phone): void
    {
        if ($this->attempts($phone) >= self::MAX_ATTEMPTS) {
            throw new SignInFailed('identity.errors.locked', 'Too many wrong codes', ['minutes' => self::LOCK_MINUTES], 429);
        }
    }

    private function attempts(string $phone): int
    {
        $attempts = $this->cache->get($this->key($phone));

        return is_int($attempts) ? $attempts : 0;
    }

    private function key(string $phone): string
    {
        return 'reos:phone-attempts:'.hash('sha256', $phone);
    }

    /** Audit rows name the number by its last four digits only. */
    private function mask(string $phone): string
    {
        return '••••'.substr($phone, -4);
    }
}
