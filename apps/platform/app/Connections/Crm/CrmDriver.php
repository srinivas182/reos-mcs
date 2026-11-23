<?php

declare(strict_types=1);

namespace App\Connections\Crm;

use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Models\Connection;

/**
 * What REOS writes to a CRM (B7.2, ADR 0028): notes, task completion and a
 * person's stage and tags. People, notes and tasks are read through the
 * driver's streams (`people`, `notes`, `tasks`).
 *
 * Normalised stream records:
 * - people: external_id, first_name, last_name, emails, phones, stage, tags, birthday, updated_at
 * - notes: external_id, person_id, body, updated_at
 * - tasks: external_id, person_id, title, due_at, done, updated_at
 *
 * @throws AccessRevoked|VendorRateLimited|VendorUnavailable from every method
 */
interface CrmDriver
{
    /** @return string The note's id in the CRM. */
    public function createNote(Connection $connection, string $token, string $personId, string $body): string;

    public function setTaskDone(Connection $connection, string $token, string $taskId, bool $done): void;

    /** @param  array{stage?: string|null, tags?: list<string>}  $fields */
    public function updatePerson(Connection $connection, string $token, string $personId, array $fields): void;
}
