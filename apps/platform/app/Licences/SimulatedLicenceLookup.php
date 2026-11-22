<?php

declare(strict_types=1);

namespace App\Licences;

use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Models\State;
use Carbon\CarbonImmutable;

/**
 * Demo licence records (A5.1), so adding a licence can be shown and tested
 * before a regulator feed is connected. Never used in production.
 *
 * The number decides the answer (digits only):
 * - ends in 0000: no such licence;
 * - ends in 9999: expired;
 * - ends in 8888: found, under someone else's name (goes to review);
 * - ends in 7777: inactive (not sponsored);
 * - starts with 9: a broker licence; otherwise a sales agent.
 * Anything else is an active licence in the account holder's name, first
 * licensed three years ago and expiring at the end of next March.
 */
final readonly class SimulatedLicenceLookup implements LicenceLookup
{
    public const ID = 'simulator';

    public function __construct(private CarbonImmutable $today) {}

    public function source(): string
    {
        return __('licences.source.simulator');
    }

    public function id(): string
    {
        return self::ID;
    }

    public function find(State $state, string $number, ?string $accountName): ?LicenceRecord
    {
        $digits = (string) preg_replace('/\D/', '', $number);

        if ($digits === '' || str_ends_with($digits, '0000')) {
            return null;
        }

        $expires = $this->today->setDate($this->today->year + 1, 3, 31)->startOfDay();
        $status = LicenceStatus::Active;

        if (str_ends_with($digits, '9999')) {
            $status = LicenceStatus::Expired;
            $expires = $this->today->subMonths(2)->endOfMonth();
        } elseif (str_ends_with($digits, '7777')) {
            $status = LicenceStatus::Inactive;
        }

        return new LicenceRecord(
            name: str_ends_with($digits, '8888') ? 'Pat Jordan' : ($accountName ?? 'REOS Demo'),
            type: str_starts_with($digits, '9') ? LicenceType::Broker : LicenceType::SalesAgent,
            status: $status,
            licensedOn: $expires->subYears(3)->addDay(),
            expiresOn: $expires->startOfDay(),
            sponsor: str_starts_with($digits, '9') ? null : 'Lone Star Realty',
        );
    }
}
