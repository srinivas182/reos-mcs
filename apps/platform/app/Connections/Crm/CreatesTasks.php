<?php

declare(strict_types=1);

namespace App\Connections\Crm;

use App\Models\Connection;
use Carbon\CarbonImmutable;

/**
 * A CRM that takes a new task for a person (B8.3): a task added in REOS's
 * contact panel goes to the CRM, and done in one is done in the other.
 * BoldTrail's public API doesn't document creating tasks, so tasks for its
 * people stay in REOS.
 */
interface CreatesTasks
{
    /** Creates the task and returns its id in the CRM. */
    public function createTask(Connection $connection, string $token, string $personId, string $title, ?CarbonImmutable $due): string;
}
