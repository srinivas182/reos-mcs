<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

/** Why someone is paid for a sale (B5.2b). */
enum PayeeRole: string
{
    /** The school that sells the product. */
    case Seller = 'seller';
    case Instructor = 'instructor';
    case Mentor = 'mentor';

    /** @return list<string> */
    public static function splitValues(): array
    {
        return [self::Instructor->value, self::Mentor->value];
    }
}
