<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a licence stands (A5.1; agent journey screens 148 and 149). */
enum LicenceStatus: string
{
    /** Checked with the regulator (or approved) and in force. */
    case Active = 'active';
    /** In force on the regulator's record but inactive (not sponsored, or by choice). */
    case Inactive = 'inactive';
    case Expired = 'expired';
    /** No regulator match yet: a reviewer decides. It doesn't count until approved. */
    case PendingReview = 'pending_review';
    case Rejected = 'rejected';

    /** Counts towards the person's states (content, CE and the switcher). */
    public function counts(): bool
    {
        return in_array($this, [self::Active, self::Inactive, self::Expired], true);
    }
}
