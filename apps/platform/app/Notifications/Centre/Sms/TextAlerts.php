<?php

declare(strict_types=1);

namespace App\Notifications\Centre\Sms;

use App\Identity\Phone\PhoneVerifierFactory;
use App\Messaging\TextMessagesFactory;
use App\Models\User;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\Consents;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Validation\ValidationException;

/**
 * Text alerts (B6.2c, ADR 0023): a verified US mobile number, the person's
 * recorded SMS consent (never assumed), and Twilio set up. Turning texts on
 * sends the confirmation text carriers require; STOP by text withdraws the
 * consent, START gives it again (see the Twilio webhook).
 */
final readonly class TextAlerts
{
    /** US numbers only (10DLC). */
    public const US_NUMBER = '/^\+1[2-9][0-9]{2}[2-9][0-9]{6}$/';

    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public function __construct(
        private Consents $consents,
        private TextMessagesFactory $texts,
        private PhoneVerifierFactory $verifiers,
        private AuditLogger $audit,
        private Cache $cache,
    ) {}

    /** Whether texts can go out at all here (Twilio set up, or the simulator outside production). */
    public function available(): bool
    {
        return $this->texts->available();
    }

    /** Only an explicit "yes" counts: SMS consent never defaults on. */
    public function consented(User $user): bool
    {
        return $this->consents->latest($user, ConsentPurpose::Sms)?->granted === true;
    }

    public function ready(User $user): bool
    {
        return $user->phone !== null && $user->phone_verified_at !== null && $this->consented($user) && $this->available();
    }

    /** @return array{phone: string|null, on: bool, available: bool} */
    public function status(User $user): array
    {
        return [
            'phone' => $user->phone !== null && $user->phone_verified_at !== null ? self::mask($user->phone) : null,
            'on' => $user->phone_verified_at !== null && $this->consented($user),
            'available' => $this->available(),
        ];
    }

    /** Sends a code to a new number (it replaces the current one once verified). */
    public function startNumber(User $user, string $phone, string $locale): void
    {
        $this->ensureUsable($user, $phone);
        $this->ensureNotLocked($user);
        $this->verifiers->make()->send($phone, $locale);
    }

    public function verifyNumber(User $user, string $phone, string $code): void
    {
        $this->ensureUsable($user, $phone);
        $this->ensureNotLocked($user);

        if (! $this->verifiers->make()->check($phone, $code)) {
            $this->cache->put($this->key($user), $this->attempts($user) + 1, now()->addMinutes(self::LOCK_MINUTES));

            throw ValidationException::withMessages(['code' => __('notifications.texts.errors.wrongCode')]);
        }

        $this->cache->forget($this->key($user));
        $before = $user->phone;
        $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
        $this->audit->record('account.phone_changed', 'user', (string) $user->id, [
            'from' => $before === null ? null : self::mask($before), 'to' => self::mask($phone),
        ], $user);
    }

    /** People who sign in with their phone keep it; others may remove it (texts stop). */
    public function removeNumber(User $user): void
    {
        if ($user->email === null) {
            throw ValidationException::withMessages(['phone' => __('notifications.texts.errors.signInPhone')]);
        }

        $before = $user->phone;
        $user->forceFill(['phone' => null, 'phone_verified_at' => null])->save();

        if ($this->consented($user)) {
            $this->consents->withdraw($user, ConsentPurpose::Sms, ConsentMethod::Web);
        }

        $this->audit->record('account.phone_changed', 'user', (string) $user->id, [
            'from' => $before === null ? null : self::mask($before), 'to' => null,
        ], $user);
    }

    /** Turns texts on (recording consent and sending the confirmation text) or off. */
    public function turn(User $user, bool $on, ConsentMethod $method): void
    {
        if ($on === $this->consented($user)) {
            return;
        }

        if (! $on) {
            $this->consents->withdraw($user, ConsentPurpose::Sms, $method);

            return;
        }

        if ($user->phone === null || $user->phone_verified_at === null) {
            throw ValidationException::withMessages(['on' => __('notifications.texts.errors.noPhone')]);
        }

        $this->consents->grant($user, ConsentPurpose::Sms, $method);
        $this->welcome($user);
    }

    /** The opt-in confirmation carriers require (program, frequency, rates, HELP and STOP). */
    public function welcome(User $user): void
    {
        $sender = $this->texts->make();

        if ($sender !== null && $user->phone !== null) {
            $sender->send($user->phone, __('notifications.texts.sms.welcome', [], $user->preferredLocale()));
        }
    }

    /** STOP, START and HELP texted to REOS's number. */
    public function stopByText(string $phone): void
    {
        foreach (User::query()->where('phone', $phone)->get() as $user) {
            if ($this->consented($user)) {
                $this->consents->withdraw($user, ConsentPurpose::Sms, ConsentMethod::Sms, note: 'Replied STOP');
            }
        }
    }

    public function startByText(string $phone): void
    {
        foreach (User::query()->where('phone', $phone)->whereNotNull('phone_verified_at')->get() as $user) {
            if (! $this->consented($user)) {
                $this->consents->grant($user, ConsentPurpose::Sms, ConsentMethod::Sms, note: 'Replied START');
            }
        }
    }

    public static function mask(string $phone): string
    {
        return '••• ••• '.substr($phone, -4);
    }

    private function ensureUsable(User $user, string $phone): void
    {
        if (preg_match(self::US_NUMBER, $phone) !== 1) {
            throw ValidationException::withMessages(['phone' => __('notifications.texts.errors.usOnly')]);
        }

        if (User::query()->where('phone', $phone)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['phone' => __('notifications.texts.errors.taken')]);
        }
    }

    private function ensureNotLocked(User $user): void
    {
        if ($this->attempts($user) >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => __('notifications.texts.errors.locked', ['minutes' => self::LOCK_MINUTES])]);
        }
    }

    private function attempts(User $user): int
    {
        $attempts = $this->cache->get($this->key($user));

        return is_int($attempts) ? $attempts : 0;
    }

    private function key(User $user): string
    {
        return 'reos:texts-number-attempts:'.$user->id;
    }
}
