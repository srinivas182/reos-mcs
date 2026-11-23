<?php

declare(strict_types=1);

namespace App\Mls;

use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Integrations\IntegrationDefinition;
use App\Mls\Reso\ResoRecords;
use App\Mls\Reso\ResoSource;
use App\Models\MlsFeed;
use App\Models\MlsListing;
use App\Models\MlsMember;
use App\Models\MlsOffice;
use App\Models\MlsOpenHouse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

/**
 * Replicates one MLS feed (B10.1, ADR 0042), every 15 minutes:
 *
 * 1. Offices, members, listings (with photos) and open houses changed since
 *    the last run, oldest first by `ModificationTimestamp`, following the
 *    vendor's next links. The cursor moves after every page, so a run that
 *    stops carries on from there (from the same timestamp: records are
 *    upserted, so repeats are harmless).
 * 2. Listings off the market (withdrawn, cancelled, expired) and records the
 *    MLS no longer shares are removed.
 * 3. Once a day, listings deleted at the MLS (gone from its keys) are removed.
 * 4. Linked agents' open houses go on their REOS calendar (B10.2).
 *
 * If the feed switches between the Simulator and the vendor, the other
 * source's records are purged first, so sample data never mixes with real.
 */
final readonly class MlsSync
{
    /** Pages per resource per run, so one MLS's first load can't hold the queue. */
    public const MAX_PAGES = 100;

    public function __construct(private MlsFeeds $feeds, private OpenHouseCalendar $calendar) {}

    /**
     * @return array{source: string|null, saved: int, removed: int}
     *
     * @throws AccessRevoked|VendorRateLimited|VendorUnavailable
     */
    public function run(IntegrationDefinition $definition): array
    {
        $feed = MlsFeed::query()->firstOrCreate(['integration' => $definition->id]);
        $source = $this->feeds->source($definition);

        if ($source === null) {
            return ['source' => null, 'saved' => 0, 'removed' => 0];
        }

        if ($feed->source !== $source->id()) {
            $this->purge($definition->id);
            $feed->forceFill(['source' => $source->id(), 'cursors' => [], 'reconciled_at' => null])->save();
        }

        $saved = 0;
        $removed = 0;

        try {
            foreach (ResoSource::RESOURCES as $resource) {
                [$s, $r] = $this->resource($feed, $source, $resource);
                $saved += $s;
                $removed += $r;
            }

            if ($feed->reconciled_at === null || $feed->reconciled_at->lt(now()->subDay())) {
                $removed += $this->reconcile($feed, $source);
            }
        } catch (AccessRevoked|VendorUnavailable $e) {
            $feed->forceFill(['last_error' => Str::limit($e->getMessage(), 480), 'failures' => $feed->failures + 1])->save();

            throw $e;
        }

        $feed->forceFill(['synced_at' => now(), 'last_error' => null, 'failures' => 0])->save();
        // Open houses on linked agents' calendars (B10.2).
        $this->calendar->syncMls($definition->id);

        return ['source' => $source->id(), 'saved' => $saved, 'removed' => $removed];
    }

    /**
     * @param  'Office'|'Member'|'Property'|'OpenHouse'  $resource
     * @return array{0: int, 1: int} saved, removed
     */
    private function resource(MlsFeed $feed, ResoSource $source, string $resource): array
    {
        $saved = 0;
        $removed = 0;
        $next = null;
        $pages = 0;

        do {
            $page = $source->changes($resource, $feed->cursors[$resource] ?? null, $next);
            $upserts = [];
            $deletes = [];
            $latest = null;
            $latestRaw = null;

            foreach ($page->records as $record) {
                $key = ResoRecords::key($resource, $record);

                if ($key === null) {
                    continue;
                }

                $columns = match ($resource) {
                    'Office' => ResoRecords::office($record),
                    'Member' => ResoRecords::member($record),
                    'Property' => ResoRecords::property($record),
                    'OpenHouse' => ResoRecords::openHouse($record),
                };

                if ($columns === null) {
                    $deletes[] = $key;
                } else {
                    $upserts[$key] = $columns;
                }

                $modified = ResoRecords::modified($record);

                if ($modified !== null && ($latest === null || $modified->gt($latest))) {
                    $latest = $modified;
                    $latestRaw = is_string($record['ModificationTimestamp'] ?? null) ? $record['ModificationTimestamp'] : $modified->format('Y-m-d\TH:i:s.v\Z');
                }
            }

            DB::transaction(function () use ($feed, $resource, $upserts, $deletes, $latestRaw): void {
                $this->save($feed->integration, $resource, $upserts);
                $this->remove($feed->integration, $resource, $deletes);

                if ($latestRaw !== null) {
                    $feed->forceFill(['cursors' => [...$feed->cursors, $resource => $latestRaw]])->save();
                }
            });

            $saved += count($upserts);
            $removed += count($deletes);
            $next = $page->next;
        } while ($next !== null && ++$pages < self::MAX_PAGES);

        return [$saved, $removed];
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows  key => columns
     */
    private function save(string $integration, string $resource, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = CarbonImmutable::now();
        [$model, $key] = match ($resource) {
            'Office' => [MlsOffice::class, 'office_key'],
            'Member' => [MlsMember::class, 'member_key'],
            'Property' => [MlsListing::class, 'listing_key'],
            default => [MlsOpenHouse::class, 'open_house_key'],
        };
        $values = [];

        foreach ($rows as $id => $columns) {
            $columns = array_map(static fn (mixed $value): mixed => $value instanceof CarbonImmutable ? $value->toDateTimeString() : $value, $columns);

            if (isset($columns['photos'])) {
                $columns['photos'] = self::json($columns['photos']);
            }

            $values[] = ['integration' => $integration, $key => $id, ...$columns, 'created_at' => $now, 'updated_at' => $now]
                + ($resource === 'Property' ? ['uuid' => (string) Str::uuid7()] : []);
        }

        $update = array_values(array_diff(array_keys($values[0]), ['uuid', 'created_at', 'integration', $key]));

        $model::query()->upsert($values, ['integration', $key], $update);
    }

    /** @param  list<string>  $keys */
    private function remove(string $integration, string $resource, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        if ($resource === 'Property') {
            $this->removeListings($integration, $keys);

            return;
        }

        [$model, $key] = match ($resource) {
            'Office' => [MlsOffice::class, 'office_key'],
            'Member' => [MlsMember::class, 'member_key'],
            default => [MlsOpenHouse::class, 'open_house_key'],
        };
        $model::query()->where('integration', $integration)->whereIn($key, $keys)->delete();
    }

    /** @param  list<string>  $keys */
    private function removeListings(string $integration, array $keys): void
    {
        MlsOpenHouse::query()->where('integration', $integration)->whereIn('listing_key', $keys)->delete();
        MlsListing::query()->where('integration', $integration)->whereIn('listing_key', $keys)->delete();
    }

    /** Listings REOS has that the MLS no longer does. */
    private function reconcile(MlsFeed $feed, ResoSource $source): int
    {
        $keep = [];
        $next = null;

        do {
            $page = $source->listingKeys($next);

            foreach ($page->records as $record) {
                $key = ResoRecords::key('Property', $record);

                if ($key !== null && ! ResoRecords::withdrawn($record)) {
                    $keep[$key] = true;
                }
            }

            $next = $page->next;
        } while ($next !== null);

        $gone = [];

        foreach (MlsListing::query()->where('integration', $feed->integration)->pluck('listing_key') as $key) {
            if (is_string($key) && ! isset($keep[$key])) {
                $gone[] = $key;
            }
        }

        foreach (array_chunk($gone, 500) as $chunk) {
            $this->removeListings($feed->integration, $chunk);
        }

        // Open houses long over aren't needed.
        MlsOpenHouse::query()->where('integration', $feed->integration)->where('ends_at', '<', now()->subDays(30))->delete();
        $feed->forceFill(['reconciled_at' => now()])->save();

        return count($gone);
    }

    private function purge(string $integration): void
    {
        DB::transaction(static function () use ($integration): void {
            foreach ([MlsOpenHouse::class, MlsListing::class, MlsMember::class, MlsOffice::class] as $model) {
                $model::query()->where('integration', $integration)->delete();
            }
        });
    }

    private static function json(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return '[]';
        }
    }
}
