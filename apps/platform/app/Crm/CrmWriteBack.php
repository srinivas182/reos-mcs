<?php

declare(strict_types=1);

namespace App\Crm;

use App\Connections\ConnectionStatus;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectorRegistry;
use App\Connections\Crm\CrmDriver;
use App\Jobs\PushToCrm;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\ContactLink;
use App\Models\ContactNote;
use App\Models\Todo;
use App\Models\User;
use App\Services\Connections\ConnectionTokens;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes from REOS to the contact's CRMs (B7.2, ADR 0028): a note written in
 * REOS, a task done or undone, a new stage. Saved in REOS at once, sent to
 * every connected CRM that knows the person (queued, retried), and
 * remembered so it isn't read back as a change.
 */
final readonly class CrmWriteBack
{
    public function __construct(private ConnectorRegistry $registry, private ConnectionTokens $tokens) {}

    public function note(Contact $contact, string $body, User $author): ContactNote
    {
        $note = ContactNote::query()->create(['contact_id' => $contact->id, 'body' => Str::limit(trim($body), 20000, ''), 'source' => Contacts::REOS, 'author_id' => $author->id]);
        PushToCrm::dispatch('note', $note->id);

        // When the queue runs at once (tests, demos), where it went is known already.
        return $note->refresh();
    }

    public function stage(Contact $contact, string $stage, User $actor): Contact
    {
        $times = $contact->field_times;
        $times['stage'] = CarbonImmutable::now()->toIso8601String();
        $times['stage_source'] = Contacts::REOS;
        $contact->forceFill(['stage' => mb_substr(trim($stage), 0, 60), 'field_times' => $times])->save();
        PushToCrm::dispatch('stage', $contact->id);

        return $contact;
    }

    /** A to-do from a CRM task was done or undone in REOS. */
    public function taskChanged(Todo $todo): void
    {
        if (DB::table('contact_tasks')->where('todo_id', $todo->id)->whereNotNull('external_id')->exists()) {
            PushToCrm::dispatch('task', $todo->id);
        }
    }

    /** The queued half: sends one change to each CRM that knows the person. */
    public function push(string $kind, int $id): void
    {
        match ($kind) {
            'note' => $this->pushNote($id),
            'stage' => $this->pushStage($id),
            'task' => $this->pushTask($id),
            default => null,
        };
    }

    private function pushNote(int $id): void
    {
        $note = ContactNote::query()->find($id);

        if ($note === null) {
            return;
        }

        $pushed = $note->pushed ?? [];

        foreach ($this->crmLinks($note->contact) as [$link, $connection, $driver]) {
            if (isset($pushed[$connection->integration_id])) {
                continue;
            }

            $pushed[$connection->integration_id] = $driver->createNote($connection, $this->tokens->accessToken($connection, $driver), $link->external_id, $note->body);
            // Saved after each CRM, so a retry never sends it twice.
            $note->forceFill(['pushed' => $pushed])->save();
        }
    }

    private function pushStage(int $id): void
    {
        $contact = Contact::query()->find($id);

        if ($contact === null || $contact->stage === null) {
            return;
        }

        foreach ($this->crmLinks($contact) as [$link, $connection, $driver]) {
            // The same value coming back is no change, so no echo to guard against.
            $driver->updatePerson($connection, $this->tokens->accessToken($connection, $driver), $link->external_id, ['stage' => $contact->stage]);
        }
    }

    private function pushTask(int $todoId): void
    {
        $todo = Todo::withTrashed()->find($todoId);
        $row = DB::table('contact_tasks')->where('todo_id', $todoId)->first();

        if ($todo === null || $row === null || ! is_string($row->external_id)) {
            return;
        }

        $contact = Contact::withTrashed()->find(is_numeric($row->contact_id) ? (int) $row->contact_id : 0);

        foreach ($contact === null ? [] : $this->crmLinks($contact) as [, $connection, $driver]) {
            if ($connection->integration_id === $row->source) {
                $driver->setTaskDone($connection, $this->tokens->accessToken($connection, $driver), $row->external_id, $todo->done_at !== null);
            }
        }
    }

    /** @return list<array{0: ContactLink, 1: Connection, 2: CrmDriver&ConnectorDriver}> */
    private function crmLinks(Contact $contact): array
    {
        $list = [];

        foreach ($contact->links()->whereNotNull('connection_id')->get() as $link) {
            $connection = Connection::query()->find($link->connection_id);

            if ($connection === null || $connection->status !== ConnectionStatus::Active) {
                continue;
            }

            $mode = $this->registry->mode($connection->integration_id);

            if ($mode !== $connection->mode || ! $this->registry->supports($connection->integration_id)) {
                continue;
            }

            $driver = $this->registry->driver($connection->integration_id, $mode);

            if ($driver instanceof CrmDriver) {
                $list[] = [$link, $connection, $driver];
            }
        }

        return $list;
    }
}
