<?php

declare(strict_types=1);

namespace App\Licences\Ce;

use App\Licences\TrecLicenceLookup;
use App\Models\CeCompletion;
use App\Models\Licence;
use App\Models\LicenceRenewal;
use App\Services\StateRules\CeRequirements;
use Carbon\CarbonImmutable;

/**
 * Nightly, after TREC's licence file is imported (A11.2, ADR 0045):
 *
 * - When TREC's record shows the licence's CE requirement met (the MCE
 *   status, or the education status on a first renewal), every completion
 *   in the period becomes "on TREC's record".
 * - When the record shows a later expiry than REOS has, the renewal is
 *   confirmed and the licence moves to its new period.
 */
final readonly class CeConfirmations
{
    public const BY = 'trec-licence';

    public function __construct(private CeRequirements $requirements) {}

    /** @return array{confirmed: int, renewed: int} */
    public function run(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $confirmed = 0;
        $renewed = 0;

        Licence::query()->where('state_code', 'TX')->whereNotNull('verified_at')->chunkById(200, function ($licences) use ($today, &$confirmed, &$renewed): void {
            foreach ($licences as $licence) {
                $record = TrecLicenceLookup::record($licence->number);
                $ce = $record === null ? null : $this->requirements->for($licence, $today);

                if ($record === null || $ce === null) {
                    continue;
                }

                $met = ($ce['first_renewal'] ? $record->education_status : $record->mce_status) === 2;

                if ($met) {
                    $confirmed += CeCompletion::query()->where('user_id', $licence->user_id)->where('state_code', $licence->state_code)->whereNull('confirmed_at')
                        ->when($ce['period_start'] !== null, static fn ($q) => $q->whereDate('completed_on', '>', $ce['period_start']))
                        ->whereDate('completed_on', '<=', $today->toDateString())
                        ->update(['confirmed_at' => now(), 'confirmed_by' => self::BY]);
                }

                $old = $licence->expires_on === null ? null : CarbonImmutable::parse($licence->expires_on);

                if ($old !== null && $record->expires_on !== null && $record->expires_on->gt($old)) {
                    $renewal = LicenceRenewal::query()->firstOrNew(['licence_id' => $licence->id, 'due_on' => $old->toDateString()]);
                    $renewal->forceFill(['confirmed_at' => now(), 'renewed_until' => $record->expires_on->toDateString(), 'renewed_at' => $renewal->renewed_at ?? now()])->save();
                    $licence->forceFill(['expires_on' => $record->expires_on->toDateString(), 'sponsor_name' => $record->sponsor_name ?? $licence->sponsor_name])->save();
                    $renewed++;
                }
            }
        });

        return ['confirmed' => $confirmed, 'renewed' => $renewed];
    }
}
