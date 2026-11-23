<?php

declare(strict_types=1);

namespace App\Deals;

use App\Models\Connection;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\Todo;
use App\Models\User;
use App\Services\Todos\Todos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Deals (B9.1, ADR 0037): one model for every transaction tool, and for
 * deals an agent tracks in REOS.
 *
 * - A deal from a tool is read-only in REOS (decision 3): the tool is the
 *   record, so changes are made there. A REOS deal can be edited and
 *   dragged between stages.
 * - Every key date is a REOS to-do on that day, linked to the deal (so it
 *   reaches the agent's calendar, B4.1). A date moved at the source moves
 *   the to-do and its pushes; a date removed removes them.
 *
 * @phpstan-type DateInput array{kind: string, label?: string|null, due_on: string, external_id?: string|null, done?: bool}
 * @phpstan-type Party array{name: string, role: string, email: string|null, phone: string|null}
 * @phpstan-type Document array{name: string, status: string, url: string|null}
 */
final readonly class Deals
{
    public function __construct(private Todos $todos) {}

    /**
     * A deal the agent tracks in REOS: new, or changed.
     *
     * @param  array{property: string, city?: string|null, side: string, stage: string, price?: int|null, commission?: float|int|string|null, parties?: list<Party>, dates?: list<DateInput>}  $values
     */
    public function save(User $user, array $values, ?Deal $deal = null): Deal
    {
        if ($deal !== null && $deal->readOnly()) {
            throw ValidationException::withMessages(['deal' => __('deals.errors.readOnly')]);
        }

        return DB::transaction(function () use ($user, $values, $deal): Deal {
            $deal ??= new Deal(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'source' => 'reos', 'documents' => []]);
            $deal->fill([
                'property' => trim($values['property']), 'city' => $values['city'] ?? null, 'side' => $values['side'], 'stage' => $values['stage'],
                'price' => $values['price'] ?? null, 'commission' => $values['commission'] ?? null, 'parties' => $values['parties'] ?? ($deal->parties ?? []),
                'closed_at' => $values['stage'] === 'closed' ? ($deal->closed_at ?? CarbonImmutable::now()) : null,
            ])->save();

            if (array_key_exists('dates', $values)) {
                $this->syncDates($deal, $values['dates']);
            }

            return $deal->refresh();
        });
    }

    /**
     * A deal from a tool (B9.1b, B9.2): matched by its id there, everything
     * replaced by what the tool says.
     *
     * @param  array{external_id: string, external_url?: string|null, property: string, city?: string|null, side: string, stage: string, price?: int|null, commission?: float|int|string|null, parties?: list<Party>, documents?: list<Document>, dates?: list<DateInput>, updated_at?: string|null}  $record
     */
    public function receive(Connection $connection, User $owner, ?int $organisationId, array $record): Deal
    {
        return DB::transaction(function () use ($connection, $owner, $organisationId, $record): Deal {
            $deal = Deal::withTrashed()->where('connection_id', $connection->id)->where('external_id', $record['external_id'])->first()
                ?? new Deal(['uuid' => (string) Str::uuid(), 'connection_id' => $connection->id, 'external_id' => $record['external_id']]);
            $deal->fill([
                'user_id' => $owner->id, 'organisation_id' => $organisationId, 'source' => $connection->integration_id, 'external_url' => $record['external_url'] ?? null,
                'property' => mb_substr(trim($record['property']), 0, 255), 'city' => $record['city'] ?? null,
                'side' => in_array($record['side'], Deal::SIDES, true) ? $record['side'] : 'buy',
                'stage' => in_array($record['stage'], Deal::STAGES, true) ? $record['stage'] : 'active',
                'price' => $record['price'] ?? null, 'commission' => $record['commission'] ?? null,
                'parties' => $record['parties'] ?? [], 'documents' => $record['documents'] ?? [],
                'closed_at' => $record['stage'] === 'closed' ? ($deal->closed_at ?? CarbonImmutable::now()) : null,
                'source_updated_at' => isset($record['updated_at']) ? CarbonImmutable::parse($record['updated_at']) : null,
            ]);
            $deal->deleted_at = null;
            $deal->save();
            $this->syncDates($deal, $record['dates'] ?? []);

            return $deal;
        });
    }

    /** A deal gone from its tool, or deleted in REOS: its dates' to-dos go too. */
    public function remove(Deal $deal): void
    {
        DB::transaction(function () use ($deal): void {
            foreach ($deal->dates()->get() as $date) {
                $this->dropTodo($date);
                $date->delete();
            }
            $deal->delete();
        });
    }

    /** Drag between stages: REOS deals only (decision 3). */
    public function moveStage(Deal $deal, string $stage): Deal
    {
        if ($deal->readOnly()) {
            throw ValidationException::withMessages(['stage' => __('deals.errors.readOnly')]);
        }

        $deal->forceFill(['stage' => $stage, 'closed_at' => $stage === 'closed' ? ($deal->closed_at ?? CarbonImmutable::now()) : null])->save();

        if ($stage === 'cancelled') {
            foreach ($deal->dates()->whereNull('done_at')->get() as $date) {
                $this->dropTodo($date);
            }
        }

        return $deal;
    }

    /**
     * The deal's key dates become what's given: matched by their id in the
     * tool, else by kind and label. A moved date moves its to-do and resets
     * its pushes; a new one gets a to-do; one no longer there loses its own.
     *
     * @param  list<DateInput>  $dates
     */
    public function syncDates(Deal $deal, array $dates): void
    {
        $existing = $deal->dates()->get();
        $kept = [];

        foreach ($dates as $input) {
            if (! in_array($input['kind'], DealDate::KINDS, true) || preg_match('/^\d{4}-\d{2}-\d{2}/', $input['due_on']) !== 1) {
                continue;
            }

            $external = $input['external_id'] ?? null;
            $label = isset($input['label']) && $input['label'] !== '' ? mb_substr($input['label'], 0, 120) : null;
            $date = $existing->first(static fn (DealDate $d): bool => $external !== null ? $d->external_id === $external : ($d->external_id === null && $d->kind === $input['kind'] && $d->label === $label && ! in_array($d->id, $kept, true)));
            $due = CarbonImmutable::parse(substr($input['due_on'], 0, 10));
            $done = ($input['done'] ?? false) === true;

            if ($date === null) {
                $date = DealDate::query()->create(['deal_id' => $deal->id, 'kind' => $input['kind'], 'label' => $label, 'due_on' => $due->toDateString(), 'external_id' => $external, 'done_at' => $done ? now() : null]);
            } else {
                $moved = $date->due_on->toDateString() !== $due->toDateString();
                $date->fill(['kind' => $input['kind'], 'label' => $label, 'due_on' => $due->toDateString(), 'done_at' => $done ? ($date->done_at ?? now()) : null]);
                if ($moved) {
                    $date->fill(['alerted_72_at' => null, 'alerted_24_at' => null]);
                }
                $date->save();
            }

            $kept[] = $date->id;
            $this->toTodo($deal, $date);
        }

        foreach ($existing->whereNotIn('id', $kept) as $gone) {
            $this->dropTodo($gone);
            $gone->delete();
        }
    }

    /** The key date's to-do, on its day: created, moved, done or reopened. Cancelled deals keep none. */
    private function toTodo(Deal $deal, DealDate $date): void
    {
        if ($deal->stage === 'cancelled') {
            $this->dropTodo($date);

            return;
        }

        $owner = $deal->user;
        $title = mb_substr(self::dateName($date, $owner).' · '.$deal->property, 0, 200);
        $todo = $date->todo_id === null ? null : Todo::withTrashed()->find($date->todo_id);

        if ($todo === null) {
            [$todo] = $this->todos->create($owner, [
                'title' => $title, 'due_date' => $date->due_on->toDateString(), 'related_type' => 'deal', 'related_id' => $deal->uuid, 'related_label' => $deal->property, 'source' => 'deal',
            ], null);
            $date->forceFill(['todo_id' => $todo->id])->save();
        } else {
            if ($todo->trashed()) {
                $this->todos->restore($todo);
            }
            $this->todos->update($todo, ['title' => $title, 'due_date' => $date->due_on->toDateString()]);
        }

        if (($date->done_at !== null) !== ($todo->done_at !== null)) {
            $this->todos->complete($todo, $date->done_at !== null);
        }
    }

    private function dropTodo(DealDate $date): void
    {
        $todo = $date->todo_id === null ? null : Todo::query()->find($date->todo_id);

        if ($todo !== null) {
            $this->todos->delete($todo);
        }
    }

    /** "Option period ends", in the agent's language, or the tool's own label for other dates. */
    public static function dateName(DealDate $date, ?User $user = null): string
    {
        if ($date->kind === 'other' && $date->label !== null) {
            return $date->label;
        }

        $locale = $user?->locale?->value;
        $name = __('deals.kinds.'.$date->kind, [], is_string($locale) ? $locale : null);

        return is_string($name) ? $name : $date->kind;
    }
}
