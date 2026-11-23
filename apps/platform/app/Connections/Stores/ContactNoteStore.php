<?php

declare(strict_types=1);

namespace App\Connections\Stores;

use App\Connections\StreamStore;
use App\Crm\Contacts;
use App\Models\Connection;
use App\Models\ContactNote;
use Carbon\CarbonImmutable;

/**
 * A CRM's notes (B7.2): each lands on the contact its person is linked to.
 * A note REOS itself sent there is recognised by its id and not added twice.
 */
final readonly class ContactNoteStore implements StreamStore
{
    public function __construct(private Contacts $contacts) {}

    public function upsert(Connection $connection, array $records): int
    {
        $written = 0;

        foreach ($records as $record) {
            $id = is_string($record['external_id'] ?? null) ? $record['external_id'] : '';
            $person = is_string($record['person_id'] ?? null) ? $record['person_id'] : '';
            $body = is_string($record['body'] ?? null) ? trim($record['body']) : '';

            if ($id === '' || $person === '' || $body === '') {
                continue;
            }

            $source = $connection->integration_id;
            $ours = ContactNote::query()->where('pushed->'.$source, $id)->exists();

            if ($ours) {
                continue;
            }

            $contact = $this->contacts->forPerson($connection, $person);
            $note = ContactNote::withTrashed()->firstOrNew(['source' => $source, 'external_id' => $id, 'contact_id' => $contact->id]);
            $note->fill(['body' => mb_substr($body, 0, 20000), 'remote_updated_at' => is_string($record['updated_at'] ?? null) ? CarbonImmutable::parse($record['updated_at']) : null]);

            if ($note->isDirty()) {
                $note->save();
                $written++;
            }
        }

        return $written;
    }

    public function remove(Connection $connection, array $externalIds): int
    {
        return ContactNote::destroy(ContactNote::query()->where('source', $connection->integration_id)->whereIn('external_id', $externalIds)
            ->whereIn('contact_id', $this->contacts->idsFor($connection))->pluck('id'));
    }

    public function forget(Connection $connection): void
    {
        ContactNote::withTrashed()->where('source', $connection->integration_id)->whereIn('contact_id', $this->contacts->idsFor($connection))->forceDelete();
    }

    public function count(Connection $connection): int
    {
        return ContactNote::query()->where('source', $connection->integration_id)->whereIn('contact_id', $this->contacts->idsFor($connection))->count();
    }
}
