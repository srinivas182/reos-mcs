<?php

declare(strict_types=1);

namespace App\Http\Resources\Connections;

use App\Connections\Calendar\CalendarChoices;
use App\Connections\ConnectionStatus;
use App\Connections\ConnectorRegistry;
use App\Integrations\Catalogue;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One connection as people see it: the tool, the account, its health and
 * when it last synced. Never the tokens.
 *
 * @property Connection $resource
 */
final class ConnectionResource extends JsonResource
{
    /** @return list<array{key: string, direction: string, records: int}> */
    private static function streams(Connection $connection): array
    {
        $streams = [];
        $registry = app(ConnectorRegistry::class);

        foreach (ConnectorRegistry::streamDirections($connection->integration_id) as $key => $direction) {
            $store = $registry->supports($connection->integration_id) ? ($registry->driver($connection->integration_id, $connection->mode)->streams()[$key] ?? null) : null;
            $streams[] = ['key' => $key, 'direction' => $direction, 'records' => $store === null ? 0 : app($store)->count($connection)];
        }

        return $streams;
    }

    /** @return list<array{id: string, name: string, primary: bool, writable: bool, mode: string, write: bool}>|null */
    private static function calendars(Connection $connection): ?array
    {
        if (! ConnectorRegistry::isCalendar($connection->integration_id)) {
            return null;
        }

        $write = CalendarChoices::writeCalendar($connection);

        return array_map(static fn ($calendar): array => [
            ...$calendar->toArray(),
            'mode' => CalendarChoices::mode($connection, $calendar->id)->value,
            'write' => $calendar->id === $write,
        ], CalendarChoices::known($connection));
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $connection = $this->resource;

        return [
            'id' => (int) $connection->id,
            /** The tool's register id, for example `google-calendar`. */
            'integration' => (string) $connection->integration_id,
            'integration_name' => (string) (app(Catalogue::class)->find($connection->integration_id)->name ?? $connection->integration_id),
            /** `user` (yours) or `organisation` (your organisation's). */
            'owner' => (string) $connection->owner_type,
            /** The account at the tool, usually its email. */
            'account' => $connection->account_label,
            /**
             * `active`, `reconnect_needed` or `failing`.
             *
             * @var ConnectionStatus
             */
            'status' => $connection->status,
            /** Running on demo data (the Simulator), not the real tool. */
            'demo' => $connection->mode->value === 'simulator',
            /** What REOS may do at the tool. */
            'scopes' => $connection->scopes,
            /** How far back the first sync reached: `3m`, `12m` or `all`. */
            'history' => $connection->history()->value,
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            /** Translation key for the latest problem, or null. */
            'problem' => $connection->last_error,
            /** When the latest problem started (for "stopped 2 days ago"). */
            'problem_since' => $connection->last_error === null ? null : $connection->last_failed_at?->toIso8601String(),
            'connected_at' => $connection->created_at?->toIso8601String(),
            /**
             * What syncs: each stream, which way, and how many records came over.
             *
             * @var list<array{key: 'events', direction: 'read'|'write'|'both', records: int}>
             */
            'streams' => self::streams($connection),
            /**
             * Calendars only (screen 16): each calendar, how REOS reads it, and whether REOS's events go there.
             *
             * @var list<array{id: string, name: string, primary: bool, writable: bool, mode: 'full'|'busy'|'off', write: bool}>|null
             */
            'calendars' => self::calendars($connection),
        ];
    }
}
