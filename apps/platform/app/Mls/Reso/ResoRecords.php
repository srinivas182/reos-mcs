<?php

declare(strict_types=1);

namespace App\Mls\Reso;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reads RESO Data Dictionary records into REOS's columns (ADR 0042). One
 * mapping for every MLS and vendor; a null means "remove it here" (off the
 * market, withdrawn by the MLS, or a cancelled open house).
 */
final class ResoRecords
{
    /** StandardStatus => REOS status; any other status is off the market. */
    public const STATUSES = [
        'Coming Soon' => 'coming_soon',
        'Active' => 'active',
        'Active Under Contract' => 'under_contract',
        'Pending' => 'pending',
        'Closed' => 'closed',
    ];

    /**
     * @param  array<mixed>  $record
     * @return array<string, mixed>
     */
    public static function fields(array $record): array
    {
        $fields = [];

        foreach ($record as $key => $value) {
            $fields[(string) $key] = $value;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>|null columns of `mls_listings`, or null to remove
     */
    public static function property(array $r): ?array
    {
        $status = self::STATUSES[self::text($r, 'StandardStatus') ?? ''] ?? null;

        if ($status === null || self::withdrawn($r)) {
            return null;
        }

        $street = self::text($r, 'UnparsedAddress') ?? trim(implode(' ', array_filter([
            self::text($r, 'StreetNumber'), self::text($r, 'StreetDirPrefix'), self::text($r, 'StreetName'), self::text($r, 'StreetSuffix'), self::text($r, 'StreetDirSuffix'),
        ])));

        return [
            'listing_id' => self::text($r, 'ListingId') ?? self::text($r, 'ListingKey'),
            'status' => $status,
            'property_type' => self::text($r, 'PropertyType'),
            'property_sub_type' => self::text($r, 'PropertySubType'),
            'street' => $street === '' ? null : $street,
            'unit' => self::text($r, 'UnitNumber'),
            'city' => self::text($r, 'City'),
            'state' => self::text($r, 'StateOrProvince'),
            'postal_code' => self::text($r, 'PostalCode'),
            'subdivision' => self::text($r, 'SubdivisionName'),
            'latitude' => self::number($r, 'Latitude'),
            'longitude' => self::number($r, 'Longitude'),
            'list_price' => self::whole($r, 'ListPrice'),
            'original_list_price' => self::whole($r, 'OriginalListPrice'),
            'close_price' => self::whole($r, 'ClosePrice'),
            'close_date' => self::date($r, 'CloseDate'),
            'bedrooms' => self::whole($r, 'BedroomsTotal'),
            'bathrooms' => self::number($r, 'BathroomsTotalDecimal') ?? self::number($r, 'BathroomsTotalInteger'),
            'living_area' => self::whole($r, 'LivingArea'),
            'lot_acres' => self::number($r, 'LotSizeAcres'),
            'year_built' => self::whole($r, 'YearBuilt'),
            'list_agent_key' => self::text($r, 'ListAgentKey'),
            'list_agent_name' => self::text($r, 'ListAgentFullName'),
            'co_list_agent_key' => self::text($r, 'CoListAgentKey'),
            'list_office_key' => self::text($r, 'ListOfficeKey'),
            'list_office_name' => self::text($r, 'ListOfficeName'),
            'remarks' => self::cut(self::text($r, 'PublicRemarks'), 4000),
            'photos' => self::photos($r['Media'] ?? null),
            'virtual_tour' => self::text($r, 'VirtualTourURLUnbranded'),
            'listed_on' => self::date($r, 'ListingContractDate'),
            'idx' => ($r['InternetEntireListingDisplayYN'] ?? true) !== false,
            'address_shown' => ($r['InternetAddressDisplayYN'] ?? true) !== false,
            'modified_at' => self::time($r, 'ModificationTimestamp'),
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>|null columns of `mls_members`
     */
    public static function member(array $r): ?array
    {
        if (self::withdrawn($r)) {
            return null;
        }

        $licence = self::text($r, 'MemberStateLicense');
        $name = self::text($r, 'MemberFullName') ?? trim(implode(' ', array_filter([self::text($r, 'MemberFirstName'), self::text($r, 'MemberLastName')])));

        return [
            'member_mls_id' => self::text($r, 'MemberMlsId'),
            'name' => $name === '' ? '—' : self::cut($name, 200),
            'licence' => $licence,
            'licence_digits' => self::licenceDigits($licence),
            'office_key' => self::text($r, 'OfficeKey'),
            'active' => (self::text($r, 'MemberStatus') ?? 'Active') === 'Active',
            'modified_at' => self::time($r, 'ModificationTimestamp'),
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>|null columns of `mls_offices`
     */
    public static function office(array $r): ?array
    {
        if (self::withdrawn($r)) {
            return null;
        }

        return [
            'office_mls_id' => self::text($r, 'OfficeMlsId'),
            'name' => self::cut(self::text($r, 'OfficeName') ?? '—', 200),
            'city' => self::text($r, 'OfficeCity'),
            'modified_at' => self::time($r, 'ModificationTimestamp'),
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>|null columns of `mls_open_houses`
     */
    public static function openHouse(array $r): ?array
    {
        $starts = self::time($r, 'OpenHouseStartTime');
        $ends = self::time($r, 'OpenHouseEndTime');

        if (self::withdrawn($r) || (self::text($r, 'OpenHouseStatus') ?? 'Active') !== 'Active' || $starts === null || $ends === null || self::text($r, 'ListingKey') === null) {
            return null;
        }

        return [
            'listing_key' => self::text($r, 'ListingKey'),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'kind' => self::text($r, 'OpenHouseType') === 'Broker' ? 'broker' : 'public',
            'remarks' => self::cut(self::text($r, 'OpenHouseRemarks'), 500),
            'modified_at' => self::time($r, 'ModificationTimestamp'),
        ];
    }

    /**
     * The record's key for each resource.
     *
     * @param  array<mixed>  $r
     */
    public static function key(string $resource, array $r): ?string
    {
        return self::text(self::fields($r), match ($resource) {
            'Office' => 'OfficeKey',
            'Member' => 'MemberKey',
            'Property' => 'ListingKey',
            default => 'OpenHouseKey',
        });
    }

    /** A licence number as digits without leading zeros, so "0798765" finds "798765". */
    public static function licenceDigits(?string $licence): ?string
    {
        $digits = ltrim((string) preg_replace('/\D/', '', (string) $licence), '0');

        return $digits === '' ? null : $digits;
    }

    /**
     * MLS Grid marks a record the MLS no longer shares with `MlgCanView` false.
     *
     * @param  array<string, mixed>  $r
     */
    public static function withdrawn(array $r): bool
    {
        return ($r['MlgCanView'] ?? true) === false;
    }

    /** @param  array<string, mixed>  $r */
    public static function modified(array $r): ?CarbonImmutable
    {
        return self::time($r, 'ModificationTimestamp');
    }

    /**
     * Photos in order, with their captions (at most 50; B10.2 applies each MLS's limit).
     *
     * @return list<array{url: string, caption: string|null}>
     */
    private static function photos(mixed $media): array
    {
        if (! is_array($media)) {
            return [];
        }

        $photos = [];

        foreach ($media as $item) {
            if (! is_array($item)) {
                continue;
            }

            $item = self::fields($item);
            $url = self::text($item, 'MediaURL');
            $category = self::text($item, 'MediaCategory');

            if ($url === null || preg_match('#^https?://#i', $url) !== 1 || ($category !== null && $category !== 'Photo') || self::withdrawn($item)) {
                continue;
            }

            $photos[] = ['order' => self::whole($item, 'Order') ?? count($photos), 'url' => $url, 'caption' => self::cut(self::text($item, 'ShortDescription'), 200)];
        }

        usort($photos, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_slice(array_map(static fn (array $photo): array => ['url' => $photo['url'], 'caption' => $photo['caption']], $photos), 0, 50);
    }

    /** @param  array<string, mixed>  $r */
    private static function text(array $r, string $key): ?string
    {
        $value = $r[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param  array<string, mixed>  $r */
    private static function number(array $r, string $key): ?float
    {
        $value = $r[$key] ?? null;

        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? (float) $value : null;
    }

    /** @param  array<string, mixed>  $r */
    private static function whole(array $r, string $key): ?int
    {
        $value = self::number($r, $key);

        return $value === null ? null : (int) round($value);
    }

    /** @param  array<string, mixed>  $r */
    private static function date(array $r, string $key): ?string
    {
        $time = self::time($r, $key);

        return $time?->toDateString();
    }

    /** @param  array<string, mixed>  $r */
    private static function time(array $r, string $key): ?CarbonImmutable
    {
        $value = self::text($r, $key);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private static function cut(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $length);
    }
}
