<?php

declare(strict_types=1);

namespace App\Crm;

use App\Models\Connection;
use App\Models\Contact;
use App\Models\ContactActivity;
use App\Models\ContactLink;
use App\Models\Tag;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One person, all sources (B7.1, ADR 0027).
 *
 * - **Matching:** a record from a source joins the contact it's already
 *   linked to; otherwise an existing contact with the same email or phone
 *   (well-formed and normalised: emails lower-cased, phones in E.164). A
 *   name alone only suggests a merge, never makes one.
 * - **Conflicts:** newest change wins, field by field. The losing value is
 *   kept in the contact's activity ("Follow Up Boss changed the phone to …;
 *   REOS's earlier value …"). Emails, phones and tags are added together.
 * - **No echoes:** what REOS last wrote to a source is fingerprinted on the
 *   link, so its own write coming back is not a change.
 * - Merges keep every link and can be split again; both are audited.
 *
 * @phpstan-type Incoming array{
 *     external_id: string,
 *     first_name?: string|null,
 *     last_name?: string|null,
 *     emails?: list<string>,
 *     phones?: list<string>,
 *     stage?: string|null,
 *     birthday?: string|null,
 *     tags?: list<string>,
 *     updated_at?: string|null
 * }
 */
final readonly class Contacts
{
    public const PHONE = 'phone';

    public const REOS = 'reos';

    /** Fields where the newest change wins. */
    private const FIELDS = ['first_name', 'last_name', 'stage', 'birthday'];

    public function __construct(private AuditLogger $audit) {}

    public static function ownerKey(string $ownerType, int $ownerId): string
    {
        return $ownerType.':'.$ownerId;
    }

    /**
     * Saves one record from a source and returns its contact.
     *
     * @param  Incoming  $record
     */
    public function receive(string $ownerType, int $ownerId, string $source, ?Connection $connection, array $record): Contact
    {
        $clean = self::clean($record);
        $key = self::ownerKey($ownerType, $ownerId);
        $at = self::time($record['updated_at'] ?? null);

        return DB::transaction(function () use ($ownerType, $ownerId, $source, $connection, $clean, $key, $at): Contact {
            $link = ContactLink::query()->where('owner_key', $key)->where('source', $source)->where('external_id', $clean['external_id'])->lockForUpdate()->first();

            // REOS's own write coming back: nothing changed.
            if ($link !== null && $link->echo_hash !== null && $link->echo_hash === self::fingerprint($clean)) {
                $link->forceFill(['synced_at' => now(), 'remote_updated_at' => $at])->save();

                return $this->current($link->contact);
            }

            if ($link !== null) {
                $contact = $this->current($link->contact);
                $link->forceFill(['synced_at' => now(), 'remote_updated_at' => $at, 'echo_hash' => null, 'connection_id' => $connection === null ? $link->connection_id : $connection->id])->save();
                $this->apply($contact, $clean, $source, $at, false);

                return $contact;
            }

            $contact = $this->match($ownerType, $ownerId, $clean);
            $created = $contact === null;
            $contact ??= Contact::query()->create([
                'uuid' => Str::uuid7()->toString(), 'owner_type' => $ownerType, 'owner_id' => $ownerId, 'emails' => [], 'phones' => [], 'field_times' => [],
            ]);

            ContactLink::query()->create([
                'contact_id' => $contact->id, 'owner_key' => $key, 'source' => $source, 'connection_id' => $connection?->id,
                'external_id' => $clean['external_id'], 'remote_updated_at' => $at, 'synced_at' => now(),
            ]);
            $this->apply($contact, $clean, $source, $at, $created);
            $this->log($contact, $created ? 'created' : 'linked', $source, [], $at);

            if ($created) {
                $this->suggest($contact);
            }

            return $contact;
        });
    }

    /**
     * The source deleted these records: the links go, the contacts stay ("removed in Follow Up Boss").
     *
     * @param  list<string>  $externalIds
     */
    public function removed(string $ownerType, int $ownerId, string $source, array $externalIds): int
    {
        $links = ContactLink::query()->where('owner_key', self::ownerKey($ownerType, $ownerId))->where('source', $source)->whereIn('external_id', $externalIds)->get();

        foreach ($links as $link) {
            $this->log($this->current($link->contact), 'removed', $source, [], CarbonImmutable::now());
            $link->delete();
        }

        return $links->count();
    }

    /**
     * A source is disconnected (or phone contacts turned off): its links go,
     * and contacts nobody else knows go with them.
     */
    public function forget(string $ownerType, int $ownerId, string $source, ?Connection $connection = null): void
    {
        $links = ContactLink::query()->where('owner_key', self::ownerKey($ownerType, $ownerId))->where('source', $source)
            ->when($connection !== null, static fn ($q) => $q->where('connection_id', $connection?->id))->get();

        DB::transaction(function () use ($links): void {
            foreach ($links as $link) {
                $contact = $link->contact;
                $link->delete();

                if (! $contact->links()->exists() && ! $contact->notes()->where('source', self::REOS)->exists()) {
                    $contact->forceDelete();
                }
            }
        });
    }

    /**
     * Records what REOS wrote to a source (B7.2), so it isn't read back as a change.
     *
     * @param  Incoming  $written
     */
    public function wrote(ContactLink $link, array $written): void
    {
        $link->forceFill(['echo_hash' => self::fingerprint(self::clean($written)), 'synced_at' => now()])->save();
    }

    /** Merges `$other` into `$contact`: every link, note, task and activity moves; the merge can be split again. */
    public function merge(Contact $contact, Contact $other, User $actor): Contact
    {
        if ($contact->id === $other->id || $contact->owner_type !== $other->owner_type || $contact->owner_id !== $other->owner_id || $other->merged_into_id !== null) {
            throw ValidationException::withMessages(['other' => __('crm.errors.merge')]);
        }

        DB::transaction(function () use ($contact, $other, $actor): void {
            $snapshot = [
                'links' => self::ids($other->links()->pluck('id')->all()),
                // Only what the merge added, so a split takes back exactly that.
                'emails' => array_values(array_diff(array_column($other->emails, 'value'), array_column($contact->emails, 'value'))),
                'phones' => array_values(array_diff(array_column($other->phones, 'value'), array_column($contact->phones, 'value'))),
                'tags' => self::ids($other->tags()->whereNotIn('tags.id', $contact->tags()->select('tags.id'))->pluck('tags.id')->all()),
            ];
            $other->links()->update(['contact_id' => $contact->id]);
            $other->notes()->update(['contact_id' => $contact->id]);
            DB::table('contact_tasks')->where('contact_id', $other->id)->update(['contact_id' => $contact->id]);
            $contact->tags()->syncWithoutDetaching($snapshot['tags']);
            $contact->forceFill([
                'emails' => self::union($contact->emails, $other->emails),
                'phones' => self::union($contact->phones, $other->phones),
                'first_name' => $contact->first_name ?? $other->first_name,
                'last_name' => $contact->last_name ?? $other->last_name,
            ])->save();
            $other->forceFill(['merged_into_id' => $contact->id, 'merge_snapshot' => $snapshot])->save();
            $other->delete();
            DB::table('contact_suggestions')->where(static fn ($q) => $q->whereIn('contact_id', [$contact->id, $other->id])->orWhereIn('other_id', [$contact->id, $other->id]))->delete();

            $this->log($contact, 'merged', self::REOS, ['name' => $other->name()], CarbonImmutable::now());
            $this->audit->record('contact.merged', 'contact', $contact->uuid, ['merged' => $other->uuid], $actor, $contact->organisation_id);
        });

        return $contact->refresh();
    }

    /** Undoes a merge: the merged contact comes back with the links, emails, phones and tags it brought. */
    public function split(Contact $merged, User $actor): Contact
    {
        $into = $merged->merged_into_id === null ? null : Contact::query()->find($merged->merged_into_id);
        $snapshot = $merged->merge_snapshot;

        if ($into === null || $snapshot === null) {
            throw ValidationException::withMessages(['contact' => __('crm.errors.split')]);
        }

        DB::transaction(function () use ($merged, $into, $snapshot, $actor): void {
            ContactLink::query()->whereIn('id', $snapshot['links'])->where('contact_id', $into->id)->update(['contact_id' => $merged->id]);
            // Emails, phones and tags the merge added go back with the merged contact.
            $into->forceFill([
                'emails' => array_values(array_filter($into->emails, static fn (array $e): bool => ! in_array($e['value'], $snapshot['emails'], true))),
                'phones' => array_values(array_filter($into->phones, static fn (array $p): bool => ! in_array($p['value'], $snapshot['phones'], true))),
            ])->save();
            $into->tags()->detach($snapshot['tags']);
            $merged->restore();
            $merged->forceFill(['merged_into_id' => null, 'merge_snapshot' => null])->save();

            $this->log($into, 'split', self::REOS, ['name' => $merged->name()], CarbonImmutable::now());
            $this->audit->record('contact.split', 'contact', $into->uuid, ['split' => $merged->uuid], $actor, $into->organisation_id);
        });

        return $merged->refresh();
    }

    /**
     * The owner's contacts, newest first.
     *
     * @return Collection<int, Contact>
     */
    public function of(string $ownerType, int $ownerId, int $limit = 200): Collection
    {
        return Contact::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->with(['links', 'tags'])
            ->orderByDesc('updated_at')->limit($limit)->get();
    }

    /**
     * Sync activity (screen 23): what changed in the owner's contacts, newest first.
     *
     * @return Collection<int, ContactActivity>
     */
    public function activity(string $ownerType, int $ownerId, int $limit = 100): Collection
    {
        return ContactActivity::query()
            ->whereIn('contact_id', Contact::withTrashed()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->select('id'))
            ->with('contact')->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * Possible duplicates: same name, different people as far as REOS can tell.
     *
     * @return list<array{id: int, contact: Contact, other: Contact}>
     */
    public function suggestions(string $ownerType, int $ownerId): array
    {
        $rows = DB::table('contact_suggestions')->whereNull('dismissed_at')
            ->whereIn('contact_id', Contact::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->select('id'))->get();
        $contacts = Contact::query()->whereIn('id', $rows->pluck('contact_id')->merge($rows->pluck('other_id'))->all())->get()->keyBy('id');
        $list = [];

        foreach ($rows as $row) {
            $a = $contacts->get(is_numeric($row->contact_id) ? (int) $row->contact_id : 0);
            $b = $contacts->get(is_numeric($row->other_id) ? (int) $row->other_id : 0);

            if ($a instanceof Contact && $b instanceof Contact) {
                $list[] = ['id' => is_numeric($row->id) ? (int) $row->id : 0, 'contact' => $a, 'other' => $b];
            }
        }

        return $list;
    }

    public function dismiss(int $suggestionId, string $ownerType, int $ownerId): void
    {
        DB::table('contact_suggestions')->where('id', $suggestionId)
            ->whereIn('contact_id', Contact::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->select('id'))
            ->update(['dismissed_at' => now(), 'updated_at' => now()]);
    }

    /** Follows merges to the contact that's shown. */
    private function current(Contact $contact): Contact
    {
        $seen = 0;

        while ($contact->merged_into_id !== null && $seen++ < 10) {
            $next = Contact::withTrashed()->find($contact->merged_into_id);

            if ($next === null) {
                break;
            }

            $contact = $next;
        }

        return $contact;
    }

    /** @param  array{external_id: string, first_name: string|null, last_name: string|null, emails: list<string>, phones: list<string>, stage: string|null, birthday: string|null, tags: list<string>}  $clean */
    private function match(string $ownerType, int $ownerId, array $clean): ?Contact
    {
        if ($clean['emails'] === [] && $clean['phones'] === []) {
            return null;
        }

        $candidates = Contact::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->get();

        return $candidates->first(static fn (Contact $c): bool => array_intersect(array_column($c->emails, 'value'), $clean['emails']) !== []
            || array_intersect(array_column($c->phones, 'value'), $clean['phones']) !== []);
    }

    /**
     * Newest change wins, field by field; the losing value goes in the activity.
     *
     * @param  array{external_id: string, first_name: string|null, last_name: string|null, emails: list<string>, phones: list<string>, stage: string|null, birthday: string|null, tags: list<string>}  $clean
     */
    private function apply(Contact $contact, array $clean, string $source, CarbonImmutable $at, bool $created): void
    {
        $times = $contact->field_times;

        foreach (self::FIELDS as $field) {
            $incoming = $clean[$field];
            $current = $field === 'birthday' ? $contact->birthday?->toDateString() : $contact->{$field};

            if ($incoming === null || $incoming === $current) {
                continue;
            }

            $stored = isset($times[$field]) ? CarbonImmutable::parse($times[$field]) : null;

            if ($current === null || $current === '' || $stored === null || ! $at->lessThan($stored)) {
                $contact->setAttribute($field, $incoming);
                $times[$field] = $at->toIso8601String();

                if (! $created && $current !== null && $current !== '') {
                    $this->log($contact, 'changed', $source, ['field' => $field, 'value' => $incoming, 'previous' => $current, 'previous_source' => $times[$field.'_source'] ?? self::REOS], $at);
                }

                $times[$field.'_source'] = $source;
            } else {
                // REOS's value is newer: the source's older value is kept in the log.
                $this->log($contact, 'kept', $source, ['field' => $field, 'value' => $current, 'previous' => $incoming], $at);
            }
        }

        $contact->emails = self::union($contact->emails, array_map(static fn (string $e): array => ['value' => $e, 'source' => $source], $clean['emails']));
        $contact->phones = self::union($contact->phones, array_map(static fn (string $p): array => ['value' => $p, 'source' => $source], $clean['phones']));
        $contact->field_times = $times;
        $contact->save();

        foreach ($clean['tags'] as $name) {
            $tag = Tag::query()->firstOrCreate(['owner_type' => $contact->owner_type, 'owner_id' => $contact->owner_id, 'name' => $name]);
            $contact->tags()->syncWithoutDetaching([$tag->id]);
        }
    }

    /** A new contact with the same name as another suggests a merge (never makes one). */
    private function suggest(Contact $contact): void
    {
        if (trim((string) $contact->first_name) === '' || trim((string) $contact->last_name) === '') {
            return;
        }

        $same = Contact::query()->where('owner_type', $contact->owner_type)->where('owner_id', $contact->owner_id)->where('id', '!=', $contact->id)
            ->whereRaw('lower(first_name) = ?', [mb_strtolower((string) $contact->first_name)])
            ->whereRaw('lower(last_name) = ?', [mb_strtolower((string) $contact->last_name)])->pluck('id');

        foreach ($same as $other) {
            DB::table('contact_suggestions')->insertOrIgnore(['contact_id' => $contact->id, 'other_id' => $other, 'reason' => 'name', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @param  array<string, string|null>  $details */
    private function log(Contact $contact, string $kind, string $source, array $details, CarbonImmutable $at): void
    {
        ContactActivity::query()->create(['contact_id' => $contact->id, 'kind' => $kind, 'source' => $source, 'details' => $details === [] ? null : $details, 'occurred_at' => $at]);
    }

    /**
     * @param  Incoming  $record
     * @return array{external_id: string, first_name: string|null, last_name: string|null, emails: list<string>, phones: list<string>, stage: string|null, birthday: string|null, tags: list<string>}
     */
    private static function clean(array $record): array
    {
        $text = static fn (mixed $v, int $max): ?string => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;
        $emails = [];
        foreach ($record['emails'] ?? [] as $email) {
            $email = mb_strtolower(trim($email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false && ! in_array($email, $emails, true) && count($emails) < 10) {
                $emails[] = $email;
            }
        }
        $phones = [];
        foreach ($record['phones'] ?? [] as $phone) {
            $e164 = PhoneNumber::normalise($phone);
            if ($e164 !== null && ! in_array($e164, $phones, true) && count($phones) < 10) {
                $phones[] = $e164;
            }
        }
        $birthday = $text($record['birthday'] ?? null, 10);
        $tags = [];
        foreach ($record['tags'] ?? [] as $tag) {
            $name = $text($tag, 60);
            if ($name !== null && ! in_array($name, $tags, true) && count($tags) < 30) {
                $tags[] = $name;
            }
        }

        return [
            'external_id' => mb_substr($record['external_id'], 0, 191),
            'first_name' => $text($record['first_name'] ?? null, 100),
            'last_name' => $text($record['last_name'] ?? null, 100),
            'emails' => $emails,
            'phones' => $phones,
            'stage' => $text($record['stage'] ?? null, 60),
            'birthday' => $birthday !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) === 1 ? $birthday : null,
            'tags' => $tags,
        ];
    }

    /** @param  array{external_id: string, first_name: string|null, last_name: string|null, emails: list<string>, phones: list<string>, stage: string|null, birthday: string|null, tags: list<string>}  $clean */
    private static function fingerprint(array $clean): string
    {
        $values = $clean;
        unset($values['external_id']);
        sort($values['emails']);
        sort($values['phones']);
        sort($values['tags']);

        return hash('sha256', (string) json_encode($values));
    }

    private static function time(?string $value): CarbonImmutable
    {
        try {
            return $value === null ? CarbonImmutable::now() : CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return CarbonImmutable::now();
        }
    }

    /**
     * @param  list<array{value: string, source: string}>  $a
     * @param  list<array{value: string, source: string}>  $b
     * @return list<array{value: string, source: string}>
     */
    private static function union(array $a, array $b): array
    {
        $values = array_column($a, 'value');

        foreach ($b as $item) {
            if (! in_array($item['value'], $values, true)) {
                $a[] = $item;
                $values[] = $item['value'];
            }
        }

        return $a;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<int>
     */
    private static function ids(array $values): array
    {
        return array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $values));
    }
}
