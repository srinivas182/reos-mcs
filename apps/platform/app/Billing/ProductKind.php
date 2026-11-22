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

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
