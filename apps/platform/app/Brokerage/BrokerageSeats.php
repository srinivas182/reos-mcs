<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\Seats;
use App\Tenancy\TenantLimits;

/**
 * Seats and plan (A15.1b, broker journey screen 4, ADR 0070): who pays for
 * agents' seats and whether AI credits are pooled. The designated broker
 * chooses; seat counts and prices stay with the plan bought on reos.com
 * (nothing is sold in the app), and credits are pooled monthly with no
 * roll-over.
 *
 * @phpstan-type SeatsView array{seats: int|null, used: int, pending: int, left: int|null, payer: 'organisation'|'member'|'split', credits_per_seat: int, credits_pooled: bool, pool: int|null, candidate_pool: int, teams: int, chosen: bool}
 */
final readonly class BrokerageSeats
{
    public function __construct(private Seats $seats, private AuditLogger $audit) {}

    /** @return SeatsView */
    public function show(Organisation $brokerage): array
    {
        $limits = $brokerage->tenantLimits();
        $usage = $this->seats->usage($brokerage);
        $payer = in_array($limits->payer, [TenantLimits::PAYER_MEMBER, TenantLimits::PAYER_SPLIT], true) ? $limits->payer : TenantLimits::PAYER_ORGANISATION;

        return [
            'seats' => $limits->seats, 'used' => $usage['used'], 'pending' => $usage['pending'], 'left' => $usage['left'],
            'payer' => $payer, 'credits_per_seat' => $limits->creditsPerSeat, 'credits_pooled' => $limits->creditsPooled,
            'pool' => $limits->creditsPooled && $limits->seats !== null ? $limits->seats * $limits->creditsPerSeat : null,
            'candidate_pool' => $limits->candidatePool, 'teams' => $brokerage->children()->count(),
            'chosen' => is_array($brokerage->limits) && array_key_exists('payer', $brokerage->limits),
        ];
    }

    /** @return SeatsView */
    public function choose(Organisation $brokerage, User $actor, string $payer, bool $pooled): array
    {
        $limits = $brokerage->limits ?? [];
        $before = ['payer' => $limits['payer'] ?? null, 'credits_pooled' => $limits['credits_pooled'] ?? null];
        $brokerage->forceFill(['limits' => [...$limits, 'payer' => $payer, 'credits_pooled' => $pooled]])->save();
        $this->audit->record('tenant.seats_chosen', 'tenant', (string) $brokerage->id, ['before' => $before, 'payer' => $payer, 'credits_pooled' => $pooled], $actor, $brokerage->id);

        return $this->show($brokerage->refresh());
    }
}
