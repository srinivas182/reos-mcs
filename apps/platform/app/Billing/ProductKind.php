<?php

declare(strict_types=1);

namespace App\Billing;

/** What a product is (B5.1, screen 67). */
enum ProductKind: string
{
    case Plan = 'plan';
    case Course = 'course';
    case Package = 'package';
    case CreditPack = 'credit_pack';
    case SeatPack = 'seat_pack';
    /** A 1-on-1 session with an instructor or mentor (A10.2a): made from their hours and price, never on the catalogue. */
    case Session = 'session';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
