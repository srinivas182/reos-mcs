<?php

declare(strict_types=1);

namespace App\Licences\Regulators;

use Carbon\CarbonImmutable;
use Generator;
use RuntimeException;
use Throwable;

/**
 * Reads a regulator's public licence file into REOS's columns (B10.3):
 *
 * - **TREC's own layout:** tab-delimited, no header, 33 fields in the order
 *   of TREC's "Public Information Files" instructions.
 * - **CSV (or tab) with a header row:** the Texas Open Data Portal's exports
 *   of TREC's and TDI's data; columns are matched by name ("License Number",
 *   `license_number` and `licensenumber` are the same column).
 *
 * Rows without a number or name are skipped. Nothing beyond the public
 * licence facts is kept.
 */
final class RegulatorFile
{
    /** TREC's licence types (field 1). */
    public const TREC_TYPES = ['SALE', 'BRK', 'BLLC', 'BCRP', '6', 'REB', 'PRIN', 'REIN', 'APIN', 'ILLC', 'ICRP', 'ERWI', 'ERWO', 'REIT'];

    /** TREC's type names, for exports that spell the type out. */
    private const TREC_TYPE_NAMES = [
        'sales agent' => 'SALE', 'salesperson' => 'SALE', 'individual broker' => 'BRK', 'broker' => 'BRK',
        'limited liability corporation broker' => 'BLLC', 'corporation broker' => 'BCRP', 'partnership broker' => '6',
        'broker organization branch' => 'REB', 'professional inspector' => 'PRIN', 'real estate inspector' => 'REIN',
        'apprentice inspector' => 'APIN',
    ];

    /** TREC's statuses (field 5) that are current; 20 and 30 are active. */
    public const TREC_ACTIVE = ['20', '30'];

    /** Normalised header => REOS column. */
    private const COLUMNS = [
        'licensetype' => 'licence_type', 'lictype' => 'licence_type', 'licencetype' => 'licence_type', 'type' => 'licence_type',
        'licensenumber' => 'number', 'licnumber' => 'number', 'licencenumber' => 'number', 'license' => 'number', 'licenseno' => 'number', 'licensenbr' => 'number', 'number' => 'number',
        'fullname' => 'name', 'name' => 'name', 'licenseename' => 'name', 'individualname' => 'name',
        'firstname' => 'first_name', 'middlename' => 'middle_name', 'lastname' => 'last_name', 'suffix' => 'suffix',
        'licensestatus' => 'status', 'licstatus' => 'status', 'status' => 'status',
        'originallicensedate' => 'licensed_on', 'licenseissuedate' => 'licensed_on', 'issuedate' => 'licensed_on', 'originalissuedate' => 'licensed_on',
        'licenseexpirationdate' => 'expires_on', 'expirationdate' => 'expires_on', 'expdate' => 'expires_on', 'expirydate' => 'expires_on',
        'educationstatus' => 'education_status', 'mcestatus' => 'mce_status',
        'designatedsupervisorflag' => 'supervisor', 'designatedsupervisor' => 'supervisor',
        'relatedlicensenumber' => 'sponsor_number', 'relatedlicensefullname' => 'sponsor_name',
        'npn' => 'npn', 'nationalproducernumber' => 'npn',
        'qualification' => 'qualification', 'linesofauthority' => 'qualification', 'loa' => 'qualification',
        'isactive' => 'active', 'active' => 'active',
    ];

    /** TREC's tab layout: field position => REOS column. */
    private const TREC_LAYOUT = [0 => 'licence_type', 1 => 'number', 2 => 'name', 3 => 'suffix', 4 => 'status', 5 => 'licensed_on', 6 => 'expires_on',
        7 => 'education_status', 8 => 'mce_status', 9 => 'supervisor', 27 => 'sponsor_number', 28 => 'sponsor_name'];

    /**
     * @param  'TREC'|'TDI'  $authority
     * @return Generator<int, array{licence_type: string, number: string, number_digits: string, name: string, status: string|null, active: bool, licensed_on: string|null, expires_on: string|null, education_status: int|null, mce_status: int|null, designated_supervisor: bool, sponsor_number: string|null, sponsor_name: string|null, npn: string|null, qualification: string|null}>
     */
    public static function rows(string $path, string $authority, string $defaultType): Generator
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Can't open {$path}.");
        }

        try {
            $first = fgets($handle);

            if ($first === false) {
                return;
            }

            $first = (string) preg_replace('/^\xEF\xBB\xBF/', '', $first);
            $delimiter = substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',';
            $cells = str_getcsv(rtrim($first, "\r\n"), $delimiter, '"', '');
            $positional = $delimiter === "\t" && in_array(strtoupper(trim((string) ($cells[0] ?? ''))), self::TREC_TYPES, true);
            $map = $positional ? self::TREC_LAYOUT : self::header($cells);

            if ($positional) {
                $row = self::row($cells, $map, $authority, $defaultType);

                if ($row !== null) {
                    yield $row;
                }
            }

            while (($line = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $row = self::row($line, $map, $authority, $defaultType);

                if ($row !== null) {
                    yield $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /** Digits without leading zeros. */
    public static function digits(?string $number): string
    {
        return ltrim((string) preg_replace('/\D/', '', (string) $number), '0');
    }

    /**
     * @param  list<string|null>  $cells
     * @return array<int, string>
     */
    private static function header(array $cells): array
    {
        $map = [];

        foreach ($cells as $i => $cell) {
            $key = self::COLUMNS[(string) preg_replace('/[^a-z0-9]/', '', strtolower((string) $cell))] ?? null;

            if ($key !== null && ! in_array($key, $map, true)) {
                $map[$i] = $key;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string|null>  $cells
     * @param  array<int, string>  $map
     * @return array{licence_type: string, number: string, number_digits: string, name: string, status: string|null, active: bool, licensed_on: string|null, expires_on: string|null, education_status: int|null, mce_status: int|null, designated_supervisor: bool, sponsor_number: string|null, sponsor_name: string|null, npn: string|null, qualification: string|null}|null
     */
    private static function row(array $cells, array $map, string $authority, string $defaultType): ?array
    {
        $v = [];

        foreach ($map as $i => $key) {
            $value = trim((string) ($cells[$i] ?? ''));
            $v[$key] = $value === '' ? null : $value;
        }

        $name = $v['name'] ?? trim(implode(' ', array_filter([$v['first_name'] ?? null, $v['middle_name'] ?? null, $v['last_name'] ?? null])));
        $name = trim($name.(isset($v['suffix']) ? ' '.$v['suffix'] : ''));
        $number = $v['number'] ?? null;

        if ($number === null || $name === '' || self::digits($number) === '') {
            return null;
        }

        $status = $v['status'] ?? null;
        $spelledActive = $status !== null && preg_match('/\bactive\b/i', $status) === 1 && preg_match('/inactive/i', $status) !== 1;
        $active = $authority === 'TREC'
            ? in_array($status, self::TREC_ACTIVE, true) || $spelledActive
            : self::truthy($v['active'] ?? null) ?? $spelledActive;
        $type = $v['licence_type'] ?? $defaultType;

        if ($authority === 'TREC') {
            $type = self::TREC_TYPE_NAMES[strtolower($type)] ?? $type;
        }

        return [
            'licence_type' => mb_substr(strtoupper($type), 0, 60),
            'number' => mb_substr($number, 0, 40),
            'number_digits' => self::digits($number),
            'name' => mb_substr($name, 0, 200),
            'status' => $status === null ? null : mb_substr($status, 0, 40),
            'active' => $active,
            'licensed_on' => self::date($v['licensed_on'] ?? null),
            'expires_on' => self::date($v['expires_on'] ?? null),
            'education_status' => self::code($v['education_status'] ?? null),
            'mce_status' => self::code($v['mce_status'] ?? null),
            'designated_supervisor' => ($v['supervisor'] ?? '0') === '1' || self::truthy($v['supervisor'] ?? null) === true,
            'sponsor_number' => isset($v['sponsor_number']) ? mb_substr($v['sponsor_number'], 0, 40) : null,
            'sponsor_name' => isset($v['sponsor_name']) ? mb_substr($v['sponsor_name'], 0, 200) : null,
            'npn' => isset($v['npn']) ? mb_substr($v['npn'], 0, 20) : null,
            'qualification' => isset($v['qualification']) ? mb_substr($v['qualification'], 0, 300) : null,
        ];
    }

    /** CCYYMMDD (TREC), MM/DD/YYYY or ISO dates. */
    private static function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            $date = match (true) {
                preg_match('/^\d{8}$/', $value) === 1 => CarbonImmutable::createFromFormat('Ymd', $value),
                preg_match('#^\d{1,2}/\d{1,2}/\d{4}#', $value) === 1 => CarbonImmutable::createFromFormat('!m/d/Y', substr($value, 0, 10)),
                default => CarbonImmutable::parse($value),
            };
        } catch (Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable && $date->year > 1900 ? $date->toDateString() : null;
    }

    private static function code(?string $value): ?int
    {
        return $value !== null && preg_match('/^\d$/', $value) === 1 ? (int) $value : null;
    }

    private static function truthy(?string $value): ?bool
    {
        return match (strtolower((string) $value)) {
            'true', 'y', 'yes', '1', 'active' => true,
            'false', 'n', 'no', '0', 'inactive' => false,
            default => null,
        };
    }
}
