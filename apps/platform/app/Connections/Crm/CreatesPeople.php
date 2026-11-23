<?php

declare(strict_types=1);

namespace App\Connections\Crm;

use App\Models\Connection;

/**
 * A CRM that takes a new person (B8.3b): a contact the agent adds in REOS
 * goes to their default CRM (Connections and Sync journey, screen 24).
 *
 * @phpstan-type NewPerson array{first_name: string|null, last_name: string|null, emails: list<string>, phones: list<string>, stage: string|null}
 */
interface CreatesPeople
{
    /**
     * Creates the person and returns their id in the CRM.
     *
     * @param  NewPerson  $person
     */
    public function createPerson(Connection $connection, string $token, array $person): string;
}
