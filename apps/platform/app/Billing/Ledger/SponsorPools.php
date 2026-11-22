<?php

declare(strict_types=1);

namespace App\Billing\Ledger;

use App\Models\Organisation;
use App\Models\SponsorCode;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Sponsor pools (B5.3, addition 6): AI credits and seats a brokerage,
 * association or school pays for its people. REOS adds to a pool on the
 * tenant's Plan tab (screen 60) when the tenant pays; sponsor codes draw
 * seats from it, and candidates' AI use draws credits from it. A tenant
 * whose seat pool has never been funded keeps the B5.1b behaviour (codes
 * REOS issues outright).
 */
final readonly class SponsorPools
{
    public function __construct(private Ledger $ledger, private AuditLogger $audit) {}

    /** @return array{credits: int, seats: int, funded: bool} */
    public function balances(Organisation $organisation): array
    {
        $credits = $this->ledger->find("organisation:{$organisation->id}:credits");
        $seats = $this->ledger->find("organisation:{$organisation->id}:seats");

        return [
            'credits' => $credits === null ? 0 : $this->ledger->balance($credits),
            'seats' => $seats === null ? 0 : $this->ledger->balance($seats),
            'funded' => $credits !== null || $seats !== null,
        ];
    }

    /** Adds what the tenant paid for (an invoice, a contract) to its pools. */
    public function fund(Organisation $organisation, int $credits, int $seats, string $note, User $actor): void
    {
        $before = $this->balances($organisation);

        if ($credits > 0) {
            $this->ledger->post('pool_funded', [[$this->ledger->platform('credits_sold'), -$credits], [$this->ledger->organisation($organisation, 'credits'), $credits]], 'organisation', (string) $organisation->id, $note);
        }

        if ($seats > 0) {
            $this->ledger->post('pool_funded', [[$this->ledger->platform('seats_issued'), -$seats], [$this->ledger->organisation($organisation, 'seats'), $seats]], 'organisation', (string) $organisation->id, $note);
        }

        $after = $this->balances($organisation);
        $this->audit->record('pool.funded', 'organisation', (string) $organisation->id, [
            'before' => ['credits' => $before['credits'], 'seats' => $before['seats']],
            'after' => ['credits' => $after['credits'], 'seats' => $after['seats']],
            'note' => $note,
        ], $actor, $organisation->id);
    }

    /** A new code takes its seats from the pool (when the tenant has one). */
    public function drawSeats(Organisation $organisation, int $seats, string $code): void
    {
        $pool = $this->ledger->find("organisation:{$organisation->id}:seats");

        if ($pool === null) {
            return;
        }

        if ($this->ledger->balance($pool) < $seats) {
            throw ValidationException::withMessages(['seats' => __('billing.pools.notEnoughSeats', ['count' => $this->ledger->balance($pool)])]);
        }

        $this->ledger->post('code_seats', [[$pool, -$seats], [$this->ledger->platform('seats_issued'), $seats]], 'sponsor_code', $code);
    }

    /** A stopped code gives its unused seats back. */
    public function returnSeats(SponsorCode $code): void
    {
        $pool = $this->ledger->find("organisation:{$code->sponsor_organisation_id}:seats");
        $unused = $code->seats - $code->used;

        if ($pool === null || $unused <= 0 || ! $this->ledger->posted('code_seats', 'sponsor_code', $code->code) || $this->ledger->posted('code_seats_returned', 'sponsor_code', $code->code)) {
            return;
        }

        $this->ledger->post('code_seats_returned', [[$this->ledger->platform('seats_issued'), -$unused], [$pool, $unused]], 'sponsor_code', $code->code);
    }
}
