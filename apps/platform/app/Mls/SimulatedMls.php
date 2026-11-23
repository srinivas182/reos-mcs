<?php

declare(strict_types=1);

namespace App\Mls;

use App\Mls\Reso\ResoPage;
use App\Mls\Reso\ResoSource;
use Carbon\CarbonImmutable;

/**
 * Demo MLS feeds (B10.1), so My listings can be shown and tested before an
 * MLS licenses REOS. Raw RESO records like a vendor's, changed earlier
 * today, so replication, paging and removals run exactly as they do live.
 * Agents see "Sample data" beside them. Never used in production.
 *
 * Houston (HAR) carries the journey's cast: James Miller (TREC 0798765),
 * Dana Brooks and Linda Park at Lone Star Realty.
 */
final readonly class SimulatedMls implements ResoSource
{
    public const PAGE_SIZE = 50;

    public function __construct(private string $dataset, private CarbonImmutable $today) {}

    public function id(): string
    {
        return 'simulator';
    }

    public function changes(string $resource, ?string $since, ?string $next): ResoPage
    {
        $offset = $next === null ? 0 : (int) substr($next, strrpos($next, ':') + 1);
        $after = $since === null ? null : CarbonImmutable::parse($since);
        $records = array_values(array_filter(
            $this->records($resource),
            static fn (array $record): bool => $after === null || CarbonImmutable::parse(self::stamp($record))->gte($after),
        ));
        usort($records, static fn (array $a, array $b): int => strcmp(self::stamp($a), self::stamp($b)));

        return $this->page($records, $offset, "simulator:{$resource}:");
    }

    public function listingKeys(?string $next): ResoPage
    {
        $offset = $next === null ? 0 : (int) substr($next, strrpos($next, ':') + 1);
        $keys = array_map(static fn (array $record): array => ['ListingKey' => $record['ListingKey']], $this->records('Property'));

        return $this->page($keys, $offset, 'simulator:keys:');
    }

    /** @param  array<string, mixed>  $record */
    private static function stamp(array $record): string
    {
        return is_string($record['ModificationTimestamp'] ?? null) ? $record['ModificationTimestamp'] : '';
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function page(array $records, int $offset, string $prefix): ResoPage
    {
        $slice = array_slice($records, $offset, self::PAGE_SIZE);
        $more = $offset + self::PAGE_SIZE < count($records);

        return new ResoPage($slice, $more ? $prefix.($offset + self::PAGE_SIZE) : null);
    }

    /** @return list<array<string, mixed>> */
    private function records(string $resource): array
    {
        $data = self::DATA[$this->dataset] ?? null;

        if ($data === null) {
            return [];
        }

        $prefix = strtoupper($this->dataset);
        $at = fn (int $hours): string => $this->today->setTime(6, 0)->subHours($hours)->utc()->format('Y-m-d\TH:i:s.v\Z');

        return match ($resource) {
            'Office' => array_map(static fn (int $i, array $o): array => [
                'OfficeKey' => "{$prefix}-O{$i}", 'OfficeMlsId' => "O{$i}", 'OfficeName' => $o[0], 'OfficeCity' => $o[1], 'ModificationTimestamp' => $at(30 + $i),
            ], array_keys($data['offices']), $data['offices']),
            'Member' => array_map(static fn (int $i, array $m): array => [
                'MemberKey' => "{$prefix}-M{$i}", 'MemberMlsId' => "M{$i}", 'MemberFullName' => $m[0], 'MemberStateLicense' => $m[1],
                'OfficeKey' => "{$prefix}-O{$m[2]}", 'MemberStatus' => 'Active', 'ModificationTimestamp' => $at(20 + $i),
            ], array_keys($data['members']), $data['members']),
            'Property' => array_map(fn (int $i, array $l): array => $this->listing($prefix, $i, $l, $data, $at(10 - $i)), array_keys($data['listings']), $data['listings']),
            'OpenHouse' => array_map(fn (int $i, array $h): array => [
                'OpenHouseKey' => "{$prefix}-H{$i}", 'ListingKey' => "{$prefix}-L{$h[0]}",
                'OpenHouseStartTime' => $this->today->addDays($h[1])->setTimeFromTimeString($h[2])->utc()->toIso8601ZuluString(),
                'OpenHouseEndTime' => $this->today->addDays($h[1])->setTimeFromTimeString($h[3])->utc()->toIso8601ZuluString(),
                'OpenHouseType' => $h[4], 'OpenHouseStatus' => 'Active', 'ModificationTimestamp' => $at(5 - $i),
            ], array_keys($data['open_houses']), $data['open_houses']),
            default => [],
        };
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string, 4: int, 5: int, 6: float, 7: int, 8: int, 9: int|null, 10: string, 11: int, 12: float, 13: float}  $l
     * @param  array{offices: array<int, array{0: string, 1: string}>, members: array<int, array{0: string, 1: string, 2: int}>}  $data
     * @return array<string, mixed>
     */
    private function listing(string $prefix, int $i, array $l, array $data, string $modified): array
    {
        [$street, $city, $zip, $status, $price, $beds, $baths, $area, $agent, $coAgent, $type, $daysListed, $latitude, $longitude] = $l;
        $member = $data['members'][$agent];
        $office = $data['offices'][$member[2]];
        $photos = array_map(static fn (int $n): array => [
            'MediaKey' => "{$prefix}-L{$i}-P{$n}", 'MediaCategory' => 'Photo', 'Order' => $n,
            'MediaURL' => url('/demo/listings/'.((($i + $n) % 6) + 1).'.svg'), 'ShortDescription' => $n === 0 ? 'Front' : null,
        ], range(0, 3));

        return array_filter([
            'ListingKey' => "{$prefix}-L{$i}", 'ListingId' => $prefix.(4_100_000 + $i * 137), 'StandardStatus' => $status,
            'PropertyType' => 'Residential', 'PropertySubType' => $type, 'UnparsedAddress' => "{$street}, {$city}, TX {$zip}",
            'City' => $city, 'StateOrProvince' => 'TX', 'PostalCode' => $zip, 'ListPrice' => $price, 'OriginalListPrice' => $price,
            'ClosePrice' => $status === 'Closed' ? (int) round($price * 0.98, -3) : null,
            'CloseDate' => $status === 'Closed' ? $this->today->subDays(max(1, $daysListed - 35))->toDateString() : null,
            'Latitude' => $latitude, 'Longitude' => $longitude,
            'BedroomsTotal' => $beds, 'BathroomsTotalDecimal' => $baths, 'LivingArea' => $area, 'YearBuilt' => 2004 + $i,
            'ListAgentKey' => "{$prefix}-M{$agent}", 'ListAgentFullName' => $member[0],
            'CoListAgentKey' => $coAgent === null ? null : "{$prefix}-M{$coAgent}",
            'ListOfficeKey' => "{$prefix}-O{$member[2]}", 'ListOfficeName' => $office[0],
            'PublicRemarks' => "Sample listing for the REOS demo: a {$beds}-bedroom home in {$city}. Not a real property.",
            'ListingContractDate' => $this->today->subDays($daysListed)->toDateString(),
            'Media' => $photos, 'ModificationTimestamp' => $modified,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Demo data per dataset: offices [name, city]; members [name, TREC licence,
     * office]; listings [street, city, zip, StandardStatus, price, beds,
     * baths, living area, list agent, co-list agent, sub-type, days listed,
     * latitude, longitude] (sold ones closed 35 days after listing);
     * open houses [listing, days ahead, start, end, type].
     */
    private const DATA = [
        'har' => [
            'offices' => [1 => ['Lone Star Realty', 'Katy'], 2 => ['Bayou City Homes', 'Houston']],
            'members' => [1 => ['James Miller', '0798765', 1], 2 => ['Dana Brooks', '0711111', 1], 3 => ['Linda Park', '9012345', 1], 4 => ['Maria Lopez', '0722222', 2]],
            'listings' => [
                1 => ['2418 Willow Bend Dr', 'Katy', '77494', 'Active', 489_000, 4, 3.0, 2850, 1, null, 'Single Family Residence', 12, 29.7858, -95.8245],
                2 => ['1107 Cypress Glen Ct', 'Katy', '77450', 'Coming Soon', 365_000, 3, 2.0, 1940, 1, null, 'Single Family Residence', 2, 29.7702, -95.7489],
                3 => ['5520 Heron Lake Ln', 'Houston', '77041', 'Pending', 412_500, 4, 2.5, 2410, 2, 1, 'Single Family Residence', 34, 29.8611, -95.5869],
                4 => ['803 Magnolia Trace', 'Katy', '77449', 'Closed', 338_000, 3, 2.0, 1780, 1, null, 'Single Family Residence', 80, 29.8126, -95.802],
                5 => ['9150 Briar Forest Dr #12', 'Houston', '77063', 'Active', 275_000, 2, 2.0, 1320, 4, null, 'Condominium', 21, 29.7488, -95.5228],
                6 => ['31 Sable Oak Ct', 'Fulshear', '77441', 'Active Under Contract', 615_000, 5, 4.0, 3720, 2, null, 'Single Family Residence', 18, 29.693, -95.8977],
                7 => ['4402 Pecan Hollow Dr', 'Katy', '77494', 'Withdrawn', 299_000, 3, 2.0, 1650, 1, null, 'Single Family Residence', 60, 29.7801, -95.8301],
                // Comparables near Willow Bend for CMAs (B10.2b).
                8 => ['2502 Willow Bend Dr', 'Katy', '77494', 'Closed', 472_000, 4, 3.0, 2780, 4, null, 'Single Family Residence', 70, 29.7866, -95.8231],
                9 => ['2611 Spring Meadow Ln', 'Katy', '77494', 'Closed', 455_000, 4, 2.5, 2690, 4, null, 'Single Family Residence', 95, 29.7891, -95.8302],
                10 => ['2207 Autumn Creek Dr', 'Katy', '77494', 'Closed', 512_000, 4, 3.5, 3010, 4, null, 'Single Family Residence', 120, 29.7812, -95.8189],
                11 => ['2930 Elm Hollow Ct', 'Katy', '77494', 'Closed', 438_000, 3, 2.5, 2480, 4, null, 'Single Family Residence', 150, 29.7920, -95.8270],
                12 => ['2215 Lakeview Bend Dr', 'Katy', '77494', 'Active', 499_000, 4, 3.0, 2920, 4, null, 'Single Family Residence', 8, 29.7839, -95.8211],
                13 => ['2738 Prairie Grove Ln', 'Katy', '77494', 'Pending', 465_000, 4, 3.0, 2710, 4, null, 'Single Family Residence', 26, 29.7905, -95.8199],
            ],
            'open_houses' => [1 => [1, 3, '13:00', '16:00', 'Public'], 2 => [2, 5, '10:00', '12:00', 'Broker']],
        ],
        'ntreis' => [
            'offices' => [1 => ['Trinity Oak Realty', 'Dallas']],
            'members' => [1 => ['Grace Kim', '0733333', 1]],
            'listings' => [
                1 => ['6120 Lakewood Blvd', 'Dallas', '75214', 'Active', 725_000, 4, 3.5, 3100, 1, null, 'Single Family Residence', 9, 32.816, -96.75],
                2 => ['2207 Preston Ridge Dr', 'Frisco', '75034', 'Pending', 549_000, 4, 3.0, 2980, 1, null, 'Single Family Residence', 27, 33.121, -96.804],
            ],
            'open_houses' => [1 => [1, 4, '14:00', '16:00', 'Public']],
        ],
        'actris' => [
            'offices' => [1 => ['Hill Country Homes', 'Austin']],
            'members' => [1 => ['Tom Reyes', '0744444', 1]],
            'listings' => [
                1 => ['1804 Barton Hills Dr', 'Austin', '78704', 'Active', 865_000, 3, 2.5, 2240, 1, null, 'Single Family Residence', 15, 30.256, -97.772],
                2 => ['400 Rio Grande St #1508', 'Austin', '78701', 'Active', 499_000, 1, 1.0, 860, 1, null, 'Condominium', 40, 30.268, -97.749],
            ],
            'open_houses' => [],
        ],
        'sabor' => [
            'offices' => [1 => ['Alamo Realty Group', 'San Antonio']],
            'members' => [1 => ['Sofia Garza', '0755555', 1]],
            'listings' => [
                1 => ['322 King William St', 'San Antonio', '78204', 'Active', 590_000, 3, 2.0, 2050, 1, null, 'Single Family Residence', 6, 29.414, -98.495],
                2 => ['15218 Stone Oak Pkwy', 'San Antonio', '78258', 'Coming Soon', 445_000, 4, 3.0, 2700, 1, null, 'Single Family Residence', 1, 29.628, -98.476],
            ],
            'open_houses' => [],
        ],
    ];
}
