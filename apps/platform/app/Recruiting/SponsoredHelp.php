<?php

declare(strict_types=1);

namespace App\Recruiting;

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Models\BrokerOffer;
use App\Models\CandidateSponsorship;
use App\Models\LicenceJourney;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sponsored AI help (A17.2, ADR 0083; broker screen 26). A brokerage's
 * offer can include AI credits for the candidate; when the candidate
 * accepts, their AI use beyond the month's free credits draws on the
 * brokerage's credit pool, up to that cap.
 *
 * - **Ends** when the candidate passes, or joins another brokerage as an
 *   agent (picks another sponsor). Checked whenever it's used or shown.
 * - **Candidates never see credit counts**, only that help is sponsored.
 * - The broker sees the pool balance, each candidate's use and status,
 *   and can change a cap.
 *
 * @phpstan-type Row array{id: string, candidate: string, cap: int, used: int, status: 'active'|'used_up'|'ended', end_reason: string|null, since: string}
 * @phpstan-type Board array{pool: int, candidates: list<Row>}
 */
final readonly class SponsoredHelp
{
    public const MAX_CAP = 5000;

    public function __construct(private Ledger $ledger, private Memberships $memberships, private AuditLogger $audit) {}

    /** The candidate accepted an offer: sponsorship starts if it includes AI help. */
    public function accepted(BrokerOffer $offer): void
    {
        if ($offer->ai_credits <= 0) {
            return;
        }

        $s = CandidateSponsorship::query()->firstOrNew(['brokerage_id' => $offer->brokerage_id, 'candidate_id' => $offer->candidate_id]);
        $s->fill(['uuid' => $s->uuid ?? (string) Str::uuid7(), 'broker_offer_id' => $offer->id, 'cap' => $offer->ai_credits, 'ended_at' => null, 'end_reason' => null])->save();
        $this->audit->record('recruiting.sponsorship_started', 'candidate_sponsorship', $s->uuid, ['cap' => $s->cap, 'candidate_id' => $offer->candidate_id], null, $offer->brokerage_id);
    }

    /** The sponsorship that can pay for this many credits now, if any. */
    public function payerFor(User $candidate, int $credits): ?CandidateSponsorship
    {
        foreach (CandidateSponsorship::query()->where('candidate_id', $candidate->id)->whereNull('ended_at')->with('brokerage')->orderBy('id')->get() as $s) {
            if (! $this->stillOn($s)) {
                continue;
            }

            $pool = $this->ledger->find("organisation:{$s->brokerage_id}:credits");

            if ($s->used + $credits <= $s->cap && $pool !== null && $this->ledger->balance($pool) >= $credits) {
                return $s;
            }
        }

        return null;
    }

    public function used(CandidateSponsorship $s, int $credits): void
    {
        $s->increment('used', $credits);
    }

    /** Whether the candidate has sponsored AI help (they never see the numbers). */
    public function sponsored(User $candidate): bool
    {
        return CandidateSponsorship::query()->where('candidate_id', $candidate->id)->whereNull('ended_at')->get()->contains(fn (CandidateSponsorship $s): bool => $this->stillOn($s));
    }

    /** @return Board */
    public function board(Organisation $brokerage): array
    {
        $rows = [];

        foreach (CandidateSponsorship::query()->where('brokerage_id', $brokerage->id)->with('candidate')->latest('id')->limit(200)->get() as $s) {
            $this->stillOn($s);
            $rows[] = [
                'id' => $s->uuid, 'candidate' => self::firstName((string) $s->candidate->name), 'cap' => $s->cap, 'used' => $s->used,
                'status' => $s->ended_at !== null ? 'ended' : ($s->used >= $s->cap ? 'used_up' : 'active'), 'end_reason' => $s->end_reason,
                'since' => $s->created_at?->toDateString() ?? '',
            ];
        }

        $pool = $this->ledger->find("organisation:{$brokerage->id}:credits");

        return ['pool' => $pool === null ? 0 : $this->ledger->balance($pool), 'candidates' => $rows];
    }

    /** @return Board */
    public function setCap(Organisation $brokerage, User $broker, string $uuid, int $cap): array
    {
        $s = CandidateSponsorship::query()->where('brokerage_id', $brokerage->id)->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException;
        $before = $s->cap;
        $s->forceFill(['cap' => max(0, min(self::MAX_CAP, $cap))])->save();
        $this->audit->record('recruiting.sponsorship_cap', 'candidate_sponsorship', $s->uuid, ['before' => $before, 'after' => $s->cap], $broker, $brokerage->id);

        return $this->board($brokerage);
    }

    /** Ends the sponsorship once the candidate has passed or joined another brokerage. */
    private function stillOn(CandidateSponsorship $s): bool
    {
        if ($s->ended_at !== null) {
            return false;
        }

        $reason = null;

        if (LicenceJourney::query()->where('user_id', $s->candidate_id)->whereNotNull('passed_on')->exists()) {
            $reason = 'passed';
        } else {
            foreach ($this->memberships->of($s->candidate) as $m) {
                $home = $m->organisation->parent_id ?? $m->organisation->id;

                if ($m->role === Role::Agent && $home !== $s->brokerage_id) {
                    $reason = 'other_sponsor';
                }
            }
        }

        if ($reason === null) {
            return true;
        }

        $s->forceFill(['ended_at' => now(), 'end_reason' => $reason])->save();
        $this->audit->record('recruiting.sponsorship_ended', 'candidate_sponsorship', $s->uuid, ['reason' => $reason], null, $s->brokerage_id);

        return false;
    }

    /** "Maria L.": the candidate's first name and initial (A10.3b). */
    public static function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return trim(($parts[0] ?? '').(count($parts) > 1 ? ' '.mb_substr((string) end($parts), 0, 1).'.' : ''));
    }
}
