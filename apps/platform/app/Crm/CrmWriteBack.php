<?php

declare(strict_types=1);

namespace App\Crm;

use App\Connections\ConnectionStatus;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectorRegistry;
use App\Connections\Crm\CreatesTasks;
use App\Connections\Crm\CrmDriver;
use App\Jobs\PushToCrm;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\ContactLink;
use App\Models\ContactNote;
use App\Models\Todo;
use App\Models\User;
use App\Services\Connections\ConnectionTokens;
use App\Services\Todos\Todos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes from REOS to the contact's CRMs (B7.2, ADR 0028): a note written in
 * REOS, a task done or undone, a new stage. Saved in REOS at once, sent to
 * every connected CRM that knows the person (queued, retried), and
 * remembered so it isn't read back as a change. A new task (B8.3) goes to
 * one CRM, the one that knows the person and synced most recently, so done
 * in REOS is done there once.
 */
final readonly class CrmWriteBack
{
    public function __construct(private ConnectorRegistry $registry, private ConnectionTokens $tokens, private Todos $todos, private Sources $sources) {}

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

    /**
     * A task about the person (the contact panel, B8.3): a REOS to-do at
     * once, and a task in the CRM that takes it. Returns the to-do and the
     * CRM's name, or null when it stays in REOS.
     *
     * @return array{0: Todo, 1: string|null}
     */
    public function task(Contact $contact, string $title, ?CarbonImmutable $due, User $agent): array
    {
        [$todo] = $this->todos->create($agent, [
            'title' => mb_substr(trim($title), 0, 200), 'due_date' => $due?->toDateString(),
            'related_type' => 'contact', 'related_id' => $contact->uuid, 'related_label' => $contact->name(), 'source' => 'typed',
        ], null);
        $target = null;

        foreach ($this->crmLinks($contact) as [$link, $connection, $driver]) {
            if ($driver instanceof CreatesTasks && ($target === null || $link->synced_at?->greaterThan($target[0]->synced_at ?? CarbonImmutable::createFromTimestamp(0)))) {
                $target = [$link, $connection];
            }
        }

        if ($target === null) {
            return [$todo, null];
        }

        DB::table('contact_tasks')->insert(['contact_id' => $contact->id, 'todo_id' => $todo->id, 'source' => $target[1]->integration_id, 'external_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        PushToCrm::dispatch('new_task', $todo->id);

        return [$todo, $this->sources->name($target[1]->integration_id)];
    }

    /**
     * "Called" or "Texted" from the agent's own phone (screens 6 and 13):
     * logged as a note, so it reaches the CRMs like any other.
     */
    public function touch(Contact $contact, string $channel, ?string $body, User $agent): ContactNote
    {
        $locale = $agent->locale->value;
        $text = match ($channel) {
            'call' => __('crm.touch.called', [], $locale),
            default => __('crm.touch.texted', ['body' => trim((string) $body)], $locale),
        };

        return $this->note($contact, $text, $agent);
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
            'new_task' => $this->pushNewTask($id),
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

    private function pushNewTask(int $todoId): void
    {
        $todo = Todo::query()->find($todoId);
        $row = DB::table('contact_tasks')->where('todo_id', $todoId)->first();

        if ($todo === null || $row === null || $row->external_id !== null) {
            return;
        }

        $contact = Contact::query()->find(is_numeric($row->contact_id) ? (int) $row->contact_id : 0);

        foreach ($contact === null ? [] : $this->crmLinks($contact) as [$link, $connection, $driver]) {
            if ($connection->integration_id === $row->source && $driver instanceof CreatesTasks) {
                $id = $driver->createTask($connection, $this->tokens->accessToken($connection, $driver), $link->external_id, $todo->title, $todo->due_date === null ? null : CarbonImmutable::instance($todo->due_date));
                DB::table('contact_tasks')->where('todo_id', $todoId)->update(['external_id' => $id, 'updated_at' => now()]);

                // Done before it reached the CRM: say so there too.
                if ($todo->done_at !== null) {
                    $driver->setTaskDone($connection, $this->tokens->accessToken($connection, $driver), $id, true);
                }

                return;
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
