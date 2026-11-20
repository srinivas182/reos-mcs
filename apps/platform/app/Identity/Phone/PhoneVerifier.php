<?php

declare(strict_types=1);

namespace App\Identity\Phone;

/** Sends and checks six-digit sign-in codes for a phone number (E.164). */
interface PhoneVerifier
{
    public function send(string $phone, string $locale): void;

    public function check(string $phone, string $code): bool;
}
