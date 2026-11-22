<?php

declare(strict_types=1);

namespace App\Licences;

use App\Models\State;

/**
 * Finds a licence in a state regulator's public records (agent journey
 * screen 149). Texas uses TREC's published licence-holder data (B10.3); until
 * then, and outside production, the Simulator answers.
 */
interface LicenceLookup
{
    /** Shown under the result: "Checked with the Texas Real Estate Commission's public licence data." */
    public function source(): string;

    /** Stored on the licence (`trec-licence`, `simulator`). */
    public function id(): string;

    /** The record for this number, or null when the regulator has none. */
    public function find(State $state, string $number, ?string $accountName): ?LicenceRecord;
}
