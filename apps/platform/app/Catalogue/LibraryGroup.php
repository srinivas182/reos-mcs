<?php

declare(strict_types=1);

namespace App\Catalogue;

/** The tabs of the Connections library (Connections and Sync journey, screens 2–4). */
enum LibraryGroup: string
{
    case Crm = 'crm';
    case Deals = 'deals';
    case Calendar = 'calendar';
    case Leads = 'leads';
    case More = 'more';

    public static function forCategory(string $category): self
    {
        return match ($category) {
            'crm' => self::Crm,
            'transactions', 'esignature' => self::Deals,
            'calendar_email', 'meetings' => self::Calendar,
            'leads' => self::Leads,
            default => self::More,
        };
    }
}
