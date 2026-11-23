<?php

declare(strict_types=1);

namespace App\Connections\Stores;

use App\Connections\StreamStore;
use App\Crm\Contacts;
use App\Models\Connection;
use App\Models\ContactLink;

/**
 * A CRM's people stream (B7.1, ADR 0027): each record is matched into the
 * owner's contacts (one person, all sources). Records are normalised by the
 * driver: external_id, first_name, last_name, emails, phones, stage,
 * birthday, tags, updated_at.
 */
final readonly class ContactStore implements StreamStore
{
    public function __construct(private Contacts $contacts) {}

    public function upsert(Connection $connection, array $records): int
    {
        $written = 0;

        foreach ($records as $record) {
            $id = $record['external_id'] ?? null;

            if (! is_string($id) && ! is_int($id)) {
                continue;
            }

            $this->contacts->receive($connection->owner_type, $connection->owner_id, $connection->integration_id, $connection, [
                'external_id' => (string) $id,
                'first_name' => self::text($record['first_name'] ?? null),
                'last_name' => self::text($record['last_name'] ?? null),
                'emails' => self::texts($record['emails'] ?? null),
                'phones' => self::texts($record['phones'] ?? null),
                'stage' => self::text($record['stage'] ?? null),
                'birthday' => self::text($record['birthday'] ?? null),
                'tags' => self::texts($record['tags'] ?? null),
                'updated_at' => self::text($record['updated_at'] ?? null),
            ]);
            $written++;
        }

        return $written;
    }

    public function remove(Connection $connection, array $externalIds): int
    {
        $removed = $this->contacts->removed($connection->owner_type, $connection->owner_id, $connection->integration_id, $externalIds);
        // People a webhook said were deleted (B7.2) are done with.
        $connection->forceFill(['meta' => array_diff_key($connection->meta, ['deleted_people' => true])])->save();

        return $removed;
    }

    public function forget(Connection $connection): void
    {
        $this->contacts->forget($connection->owner_type, $connection->owner_id, $connection->integration_id, $connection);
    }

    public function count(Connection $connection): int
    {
        return ContactLink::query()->where('connection_id', $connection->id)->count();
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /** @return list<string> */
    private static function texts(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }
}
