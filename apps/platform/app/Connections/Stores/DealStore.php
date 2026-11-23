<?php

declare(strict_types=1);

namespace App\Connections\Stores;

use App\Connections\StreamStore;
use App\Crm\RecordOwners;
use App\Deals\Deals;
use App\Models\Connection;
use App\Models\Deal;

/**
 * A transaction tool's deals stream (B9.1b, ADR 0037). Each record goes to
 * the agent who owns it: on a brokerage's tool (Lone Wolf, SkySlope), the
 * brokerage's agent whose email matches; on an agent's own (dotloop), that
 * agent. Records owned by no one at the brokerage are left out and listed
 * for screen 40.
 */
final readonly class DealStore implements StreamStore
{
    public function __construct(private Deals $deals, private RecordOwners $owners) {}

    public function upsert(Connection $connection, array $records): int
    {
        $written = 0;
        $unmatched = [];
        $organisation = $connection->owner_type === Connection::OWNER_ORGANISATION ? $connection->owner_id : null;

        foreach ($records as $record) {
            $id = $record['external_id'] ?? null;
            $property = $record['property'] ?? null;

            if ((! is_string($id) && ! is_int($id)) || ! is_string($property)) {
                continue;
            }

            $owner = $this->owners->of($connection, $record);
            $agent = $owner === null ? null : $owner[2];

            if ($agent === null) {
                $unmatched[] = is_string($record['owner_email'] ?? null) ? mb_strtolower($record['owner_email']) : '';

                continue;
            }

            $this->deals->receive($connection, $agent, $organisation, [
                'external_id' => (string) $id,
                'external_url' => self::text($record['external_url'] ?? null),
                'property' => $property,
                'city' => self::text($record['city'] ?? null),
                'side' => self::text($record['side'] ?? null) ?? 'buy',
                'stage' => self::text($record['stage'] ?? null) ?? 'active',
                'price' => is_int($record['price'] ?? null) ? $record['price'] : null,
                'commission' => is_string($record['commission'] ?? null) ? $record['commission'] : null,
                'parties' => self::parties($record['parties'] ?? null),
                'documents' => self::documents($record['documents'] ?? null),
                'dates' => self::dates($record['dates'] ?? null),
                'updated_at' => self::text($record['updated_at'] ?? null),
            ]);
            $written++;
        }

        if (RecordOwners::perRecord($connection) && $unmatched !== []) {
            $known = array_values(array_filter((array) ($connection->meta['unmatched_owners'] ?? []), is_string(...)));
            $connection->forceFill(['meta' => [...$connection->meta, 'unmatched_owners' => array_slice(array_values(array_unique([...$known, ...array_filter($unmatched)])), 0, 50)]])->save();
        }

        return $written;
    }

    public function remove(Connection $connection, array $externalIds): int
    {
        $removed = 0;

        foreach (Deal::query()->where('connection_id', $connection->id)->whereIn('external_id', $externalIds)->get() as $deal) {
            $this->deals->remove($deal);
            $removed++;
        }

        return $removed;
    }

    public function forget(Connection $connection): void
    {
        foreach (Deal::query()->where('connection_id', $connection->id)->get() as $deal) {
            $this->deals->remove($deal);
        }
    }

    public function count(Connection $connection): int
    {
        return Deal::query()->where('connection_id', $connection->id)->count();
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<array{name: string, role: string, email: string|null, phone: string|null}> */
    private static function parties(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $p) {
            if (is_array($p) && is_string($p['name'] ?? null)) {
                $out[] = ['name' => mb_substr($p['name'], 0, 120), 'role' => is_string($p['role'] ?? null) ? mb_substr($p['role'], 0, 60) : 'Contact', 'email' => self::text($p['email'] ?? null), 'phone' => self::text($p['phone'] ?? null)];
            }
        }

        return $out;
    }

    /** @return list<array{name: string, status: string, url: string|null}> */
    private static function documents(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $d) {
            if (is_array($d) && is_string($d['name'] ?? null)) {
                $status = $d['status'] ?? null;
                $out[] = ['name' => mb_substr($d['name'], 0, 200), 'status' => in_array($status, ['complete', 'missing', 'pending'], true) ? $status : 'complete', 'url' => self::text($d['url'] ?? null)];
            }
        }

        return $out;
    }

    /** @return list<array{kind: string, label: string|null, due_on: string, external_id: string|null}> */
    private static function dates(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $d) {
            if (is_array($d) && is_string($d['kind'] ?? null) && is_string($d['due_on'] ?? null)) {
                $out[] = ['kind' => $d['kind'], 'label' => self::text($d['label'] ?? null), 'due_on' => $d['due_on'], 'external_id' => self::text($d['external_id'] ?? null)];
            }
        }

        return $out;
    }
}
