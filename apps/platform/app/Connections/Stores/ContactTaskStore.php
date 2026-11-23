<?php

declare(strict_types=1);

namespace App\Connections\Stores;

use App\Connections\StreamStore;
use App\Crm\Contacts;
use App\Crm\RecordOwners;
use App\Models\Connection;
use App\Models\Todo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A CRM's tasks (B7.2): each is a REOS to-do about the contact, for the
 * person who connected the CRM (on a brokerage's CRM, the agent who owns
 * the task, B8.2). Done in one is done in the other.
 */
final readonly class ContactTaskStore implements StreamStore
{
    public function __construct(private Contacts $contacts, private RecordOwners $owners) {}

    public function upsert(Connection $connection, array $records): int
    {
        $perRecord = RecordOwners::perRecord($connection);
        $connectedBy = $connection->contact();

        if (! $perRecord && ! $connectedBy instanceof User) {
            return 0;
        }

        $written = 0;

        foreach ($records as $record) {
            $id = is_string($record['external_id'] ?? null) ? $record['external_id'] : '';
            $person = is_string($record['person_id'] ?? null) ? $record['person_id'] : '';
            $title = is_string($record['title'] ?? null) ? trim($record['title']) : '';

            if ($id === '' || $person === '' || $title === '') {
                continue;
            }

            // A brokerage's CRM (B8.2): the task's owner works it, on a person already theirs.
            $user = $connectedBy;
            $contact = null;
            if ($perRecord) {
                $user = $this->owners->of($connection, $record)[2] ?? null;
                $personLink = RecordOwners::linkFor($connection, $person);
                $contact = $personLink === null ? null : $this->contacts->contactOf($personLink);
                if (! $user instanceof User || $contact === null) {
                    continue;
                }
            }
            $contact ??= $this->contacts->forPerson($connection, $person);
            $link = DB::table('contact_tasks')->where('source', $connection->integration_id)->where('external_id', $id)->whereIn('contact_id', $this->contacts->idsFor($connection))->first();
            $todo = $link === null ? new Todo : Todo::withTrashed()->find(is_numeric($link->todo_id) ? (int) $link->todo_id : 0);

            if ($todo === null) {
                continue;
            }

            $due = is_string($record['due_at'] ?? null) ? CarbonImmutable::parse($record['due_at']) : null;
            $dated = is_string($record['due_at'] ?? null) && strlen($record['due_at']) === 10;
            $done = ($record['done'] ?? false) === true;
            $todo->forceFill([
                'user_id' => $user->id, 'title' => mb_substr($title, 0, 200), 'source' => 'crm',
                'due_at' => $dated ? null : $due, 'due_date' => $dated ? $due?->toDateString() : null,
                'related_type' => 'contact', 'related_id' => $contact->uuid, 'related_label' => $contact->name(),
                'done_at' => $done ? ($todo->done_at ?? now()) : null,
            ]);

            if ($todo->isDirty()) {
                $todo->save();
                $written++;
            }

            if ($link === null) {
                DB::table('contact_tasks')->insert(['contact_id' => $contact->id, 'todo_id' => $todo->id, 'source' => $connection->integration_id, 'external_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        return $written;
    }

    public function remove(Connection $connection, array $externalIds): int
    {
        $todos = DB::table('contact_tasks')->where('source', $connection->integration_id)->whereIn('external_id', $externalIds)->whereIn('contact_id', $this->contacts->idsFor($connection))->pluck('todo_id');

        return Todo::destroy($todos);
    }

    public function forget(Connection $connection): void
    {
        $todos = DB::table('contact_tasks')->where('source', $connection->integration_id)->whereIn('contact_id', $this->contacts->idsFor($connection))->pluck('todo_id');
        Todo::withTrashed()->whereIn('id', $todos)->forceDelete();
    }

    public function count(Connection $connection): int
    {
        return DB::table('contact_tasks')->where('source', $connection->integration_id)->whereIn('contact_id', $this->contacts->idsFor($connection))->count();
    }
}
