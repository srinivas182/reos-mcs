<?php

declare(strict_types=1);

namespace App\Services\Licences;

/** What happened when a licence was added (agent journey screen 149). */
enum LicenceCheck: string
{
    /** The regulator has it, in the account holder's name: saved and counting. */
    case Verified = 'verified';
    /** The regulator has it under another name: a reviewer decides. */
    case NameMismatch = 'name_mismatch';
    /** The regulator has no such licence; the person asked for a review. */
    case NotFound = 'not_found';
    /** No regulator check for this state yet: a reviewer decides. */
    case NoLookup = 'no_lookup';
}
