<?php

declare(strict_types=1);

namespace App\Integrations\Checks;

/** The result of testing an integration's keys against the vendor. */
enum CheckOutcome: string
{
    /** The vendor accepted the keys. */
    case Passed = 'passed';
    /** The vendor rejected the keys, or couldn't be reached. */
    case Failed = 'failed';
    /** The keys are complete and well formed, but there is no automatic check for this vendor yet. */
    case Unverified = 'unverified';

    /** Passed and unverified both allow going live; a failure doesn't. */
    public function allowsLive(): bool
    {
        return $this !== self::Failed;
    }
}
