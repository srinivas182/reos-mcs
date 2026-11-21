<?php

declare(strict_types=1);

namespace App\Tenancy;

/** Where a support sign-in request stands (A4.1c). */
enum SupportAccessStatus: string
{
    /** Waiting for the tenant's owner (24 hours). */
    case Pending = 'pending';
    /** The owner said yes; support can start within 24 hours. */
    case Approved = 'approved';
    case Declined = 'declined';
    /** Support is inside the tenant now (60 minutes). */
    case Active = 'active';
    /** Support ended the session. */
    case Ended = 'ended';
    /** Nobody answered in time, or the 60 minutes ran out. */
    case Expired = 'expired';
    /** The owner ended access. */
    case Revoked = 'revoked';

    public function open(): bool
    {
        return in_array($this, [self::Pending, self::Approved, self::Active], true);
    }
}
