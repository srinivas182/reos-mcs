<?php

declare(strict_types=1);

namespace App\Communities;

use App\Compliance\Wording;

/**
 * Checks a community post before it goes up (B17.2): REOS's advertising
 * wording (fair housing and misleading claims, B12.1), no payment for
 * referrals (RESPA §8), and no phone numbers, email addresses or links
 * (contacts are shared through connections, B16.2).
 */
final class PostCheck
{
    /** Wording that ties money to referrals (RESPA §8: no fee or thing of value for a referral). */
    public const REFERRAL_PAY = [
        'referral fee', 'referral fees', 'kickback', 'pay per referral', 'paid per referral', 'per closed referral', 'finder\'s fee', 'finders fee', 'gift card for referrals', 'per referral',
        'comisión por referido', 'pago por referido', 'tarifa de referido',
    ];

    /**
     * The problems, each with its code and the phrase.
     *
     * @return list<array{code: string, phrase: string}>
     */
    public static function issues(string $text): array
    {
        $out = [];

        foreach (Wording::find($text) as $found) {
            $out[] = ['code' => $found['code'], 'phrase' => $found['phrase']];
        }

        $lower = mb_strtolower($text);

        foreach (self::REFERRAL_PAY as $phrase) {
            if (str_contains($lower, $phrase)) {
                $out[] = ['code' => 'referral_pay', 'phrase' => $phrase];
            }
        }

        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $text, $m) === 1 || preg_match('/(?:https?:\/\/|www\.)\S+/iu', $text, $m) === 1 || preg_match('/(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/u', $text, $m) === 1) {
            $out[] = ['code' => 'contact', 'phrase' => $m[0]];
        }

        return $out;
    }
}
