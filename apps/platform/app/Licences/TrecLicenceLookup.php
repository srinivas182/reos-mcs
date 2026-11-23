<?php

declare(strict_types=1);

namespace App\Licences;

use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Licences\Regulators\RegulatorFile;
use App\Models\RegulatorRecord;
use App\Models\State;

/**
 * Texas licences from TREC's own license-holder data (B10.3, decision 8),
 * imported nightly: a number is checked against the table in milliseconds.
 *
 * - Types: SALE is a sales agent; BRK, BLLC, BCRP and partnership brokers are brokers.
 * - Statuses: 20 and 30 (current, on probation) are active; 21 and 31 are
 *   inactive (not sponsored); expired, suspended, relinquished, revoked and
 *   deceased don't count, so they read as expired.
 * - The sponsor is TREC's related licence (the sponsoring broker).
 */
final readonly class TrecLicenceLookup implements LicenceLookup
{
    public const ID = 'trec-licence';

    /** TREC's agent and broker licence types, individual first. */
    public const TYPES = ['SALE', 'BRK', 'BLLC', 'BCRP', '6'];

    public function source(): string
    {
        return __('licences.source.trec');
    }

    public function id(): string
    {
        return self::ID;
    }

    /** The agent or broker row on TREC's nightly file for a licence number, active first. */
    public static function record(string $number): ?RegulatorRecord
    {
        $digits = RegulatorFile::digits($number);

        return $digits === '' ? null : RegulatorRecord::query()->where('authority', 'TREC')->where('kind', 'agents')->where('number_digits', $digits)
            ->whereIn('licence_type', self::TYPES)->get()
            ->sortBy(static fn (RegulatorRecord $r): int => ($r->active ? 0 : 10) + (int) array_search($r->licence_type, self::TYPES, true))
            ->first();
    }

    public function find(State $state, string $number, ?string $accountName): ?LicenceRecord
    {
        $digits = RegulatorFile::digits($number);

        if ($digits === '' || $state->code !== 'TX') {
            return null;
        }

        $record = self::record($number);

        if ($record === null) {
            return null;
        }

        return new LicenceRecord(
            name: $record->name,
            type: $record->licence_type === 'SALE' ? LicenceType::SalesAgent : LicenceType::Broker,
            status: match ($record->status) {
                '20', '30' => LicenceStatus::Active,
                '21', '31' => LicenceStatus::Inactive,
                default => $record->active ? LicenceStatus::Active : LicenceStatus::Expired,
            },
            licensedOn: $record->licensed_on,
            expiresOn: $record->expires_on,
            sponsor: $record->sponsor_name,
        );
    }
}
