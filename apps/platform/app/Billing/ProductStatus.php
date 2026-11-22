<?php

declare(strict_types=1);

namespace App\Billing;

/** Draft products aren't sold; retired ones stop selling but keep their buyers' access. */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
    case Retired = 'retired';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
