<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * What people see for a tool (web journey screen 64): the register's `launch`
 * value until the super admin changes it.
 */
enum CatalogueStatus: string
{
    /** Connect / Manage. */
    case Enabled = 'enabled';
    /** Selected organisations connect; others can ask to join. */
    case Beta = 'beta';
    /** Shown with "Notify me"; taps are counted to prioritise the build. */
    case ComingSoon = 'coming_soon';
    /** Kill switch: "Temporarily unavailable". Data is kept; syncing stops. */
    case Paused = 'paused';
    /** Shown with its end date, then removed. */
    case Retired = 'retired';

    /** Whether connections may sync. */
    public function syncs(): bool
    {
        return in_array($this, [self::Enabled, self::Beta, self::Retired], true);
    }
}
