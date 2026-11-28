<?php

declare(strict_types=1);

namespace App\Licences\Journey;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\BrokerOffer;
use App\Models\LicenceJourney;
use App\Models\Organisation;
use App\Models\SessionBooking;
use App\Models\User;
use App\Notifications\BrokerOfferNotice;
use App\Recruiting\SponsoredHelp;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Sessions\SessionBookings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Broker offers (A10.3b, ADR 0041; candidate screen 17, decision 5). Only
 * candidates who opt in from their licence journey are shown to brokerages
 * that work in their state, as a first name, city and stage: never contact
 * details or scores. A brokerage makes one offer (the office, the agent's
 * split, fees, training, mentoring) on the web. The candidate sees offers
 * ranked by fit (the office in their city), rating (the brokerage's
 * mentors' session ratings) and terms; Accept shares their contact details
 * with that brokerage, Decline hides the offer.
 *
 * @phpstan-type CandidateRow array{id: int, first_name: string, city: string|null, stage: 'studying'|'exam_booked'|'passed', offer: OfferRow|null}
 * @phpstan-type OfferRow array{id: string, brokerage: string, office_city: string, split_percent: int, monthly_fee_cents: int, fees: string|null, training: string|null, mentoring: bool, ai_help: bool, ai_credits: int|null, message: string|null, status: 'open'|'accepted'|'declined'|'withdrawn', rating: float|null, ratings: int, fit: bool, contact: array{name: string, email: string, phone: string|null}|null}
 */
final readonly class BrokerOffers
{
    public function __construct(private LicenceJourneys $journeys, private Memberships $memberships, private AuditLogger $audit) {}

    /** The candidate opts in (with the city they want to work in) or out. */
    public function optIn(User $candidate, bool $on, ?string $city): void
    {
        $journey = $this->journeys->journey($candidate);

        if ($on && ($city === null || trim($city) === '')) {
            throw ValidationException::withMessages(['city' => __('licence.offers.errors.city')]);
        }

        $journey->forceFill(['offers_opt_in' => $on, 'offers_city' => $on ? trim((string) $city) : $journey->offers_city, 'offers_opted_at' => $on ? now() : null])->save();
    }

    /**
     * Candidates who opted in, in the brokerage's states (recruiting).
     *
     * @return list<CandidateRow>
     */
    public function candidates(Organisation $brokerage): array
    {
        $offers = BrokerOffer::query()->where('brokerage_id', $brokerage->id)->with(['organisation', 'candidate'])->get()->keyBy('candidate_id');
        $journeys = LicenceJourney::query()->where('offers_opt_in', true)->whereIn('state_code', $brokerage->states ?? [])->with('user')->latest('offers_opted_at')->limit(200)->get();
        $rows = [];

        foreach ($journeys as $journey) {
            $offer = $offers->get($journey->user_id);
            $rows[] = [
                'id' => $journey->user_id,
                'first_name' => PassAnnouncements::firstName((string) $journey->user->name),
                'city' => $journey->offers_city,
                'stage' => $journey->passed_on !== null ? 'passed' : ($journey->exam_on !== null ? 'exam_booked' : 'studying'),
                'offer' => $offer === null ? null : $this->row($offer, forBrokerage: true),
            ];
        }

        return $rows;
    }

    /**
     * Makes or changes the brokerage's offer while it's open (audited).
     *
     * @param  array{office_city: string, split_percent: int, monthly_fee_cents: int, fees: string|null, training: string|null, mentoring: bool, message: string|null}  $terms
     */
    public function make(Organisation $brokerage, User $broker, int $candidateId, array $terms): BrokerOffer
    {
        $journey = LicenceJourney::query()->where('user_id', $candidateId)->where('offers_opt_in', true)->first();

        if ($journey === null || ! in_array($journey->state_code, $brokerage->states ?? [], true)) {
            throw new NotFoundHttpException;
        }

        $offer = BrokerOffer::query()->where('brokerage_id', $brokerage->id)->where('candidate_id', $candidateId)->first();

        if ($offer !== null && in_array($offer->status, [BrokerOffer::ACCEPTED, BrokerOffer::DECLINED], true)) {
            throw ValidationException::withMessages(['offer' => __('licence.offers.errors.answered')]);
        }

        $new = $offer === null || $offer->status === BrokerOffer::WITHDRAWN;
        $offer ??= new BrokerOffer(['uuid' => (string) Str::uuid(), 'brokerage_id' => $brokerage->id, 'candidate_id' => $candidateId]);
        $offer->fill([...$terms, 'made_by' => $broker->id, 'status' => BrokerOffer::OPEN])->save();

        $this->audit->record($new ? 'recruiting.offer_made' : 'recruiting.offer_changed', 'broker_offer', $offer->uuid, [
            'candidate_id' => $candidateId, 'split_percent' => $offer->split_percent, 'monthly_fee_cents' => $offer->monthly_fee_cents, 'mentoring' => $offer->mentoring,
        ], $broker, $brokerage->id);

        if ($new) {
            $offer->load(['organisation', 'candidate'])->candidate->notify(new BrokerOfferNotice($offer, BrokerOfferNotice::OFFER));
        }

        return $offer;
    }

    /** The brokerage takes an open offer back (audited). */
    public function withdraw(Organisation $brokerage, User $broker, BrokerOffer $offer): void
    {
        if ($offer->brokerage_id !== $brokerage->id) {
            throw new NotFoundHttpException;
        }

        if ($offer->status !== BrokerOffer::OPEN) {
            throw ValidationException::withMessages(['offer' => __('licence.offers.errors.answered')]);
        }

        $offer->forceFill(['status' => BrokerOffer::WITHDRAWN])->save();
        $this->audit->record('recruiting.offer_withdrawn', 'broker_offer', $offer->uuid, ['candidate_id' => $offer->candidate_id], $broker, $brokerage->id);
    }

    /**
     * The candidate's offers, best first: fit, then rating, then terms.
     *
     * @return array{opted_in: bool, city: string|null, offers: list<OfferRow>}
     */
    public function forCandidate(User $candidate): array
    {
        $journey = $this->journeys->journey($candidate);
        $offers = BrokerOffer::query()->where('candidate_id', $candidate->id)->whereIn('status', [BrokerOffer::OPEN, BrokerOffer::ACCEPTED])->with(['organisation', 'candidate'])->get();
        $rows = array_map(fn (BrokerOffer $o): array => $this->row($o, forBrokerage: false, city: $journey->offers_city), $offers->all());

        usort($rows, static fn (array $a, array $b): int => self::score($b) <=> self::score($a));

        return ['opted_in' => $journey->offers_opt_in, 'city' => $journey->offers_city, 'offers' => $rows];
    }

    /** Accept: the brokerage gets the candidate's contact details. */
    public function accept(User $candidate, BrokerOffer $offer): void
    {
        $this->answer($candidate, $offer, BrokerOffer::ACCEPTED);
        // Sponsored AI help starts with the accepted offer (A17.2).
        app(SponsoredHelp::class)->accepted($offer);
    }

    /** Decline: the offer is hidden. */
    public function decline(User $candidate, BrokerOffer $offer): void
    {
        $this->answer($candidate, $offer, BrokerOffer::DECLINED);
    }

    /** The brokerage the broker manages in their active role. */
    public function brokerageOf(User $broker): Organisation
    {
        foreach ($this->memberships->of($broker) as $membership) {
            if ($membership->role === Role::Broker && $membership->organisation->type === OrganisationType::Brokerage) {
                return $membership->organisation;
            }
        }

        throw new NotFoundHttpException;
    }

    /** @return OfferRow */
    public function row(BrokerOffer $offer, bool $forBrokerage, ?string $city = null): array
    {
        [$rating, $ratings] = $this->rating($offer->organisation);
        $candidate = $offer->candidate;

        return [
            'id' => $offer->uuid,
            'brokerage' => $offer->organisation->name,
            'office_city' => $offer->office_city,
            'split_percent' => $offer->split_percent,
            'monthly_fee_cents' => $offer->monthly_fee_cents,
            'fees' => $offer->fees,
            'training' => $offer->training,
            'mentoring' => $offer->mentoring,
            // Sponsored AI help (A17.2): the candidate sees only that it's included, the brokerage the credits.
            'ai_help' => $offer->ai_credits > 0,
            'ai_credits' => $forBrokerage ? $offer->ai_credits : null,
            'message' => $offer->message,
            'status' => match ($offer->status) {
                BrokerOffer::ACCEPTED => 'accepted',
                BrokerOffer::DECLINED => 'declined',
                BrokerOffer::WITHDRAWN => 'withdrawn',
                default => 'open',
            },
            'rating' => $rating,
            'ratings' => $ratings,
            'fit' => $city !== null && mb_strtolower(trim($city)) === mb_strtolower(trim($offer->office_city)),
            // Contact details only for the brokerage, and only after Accept.
            'contact' => $forBrokerage && $offer->status === BrokerOffer::ACCEPTED
                ? ['name' => (string) $candidate->name, 'email' => (string) $candidate->email, 'phone' => $candidate->phone]
                : null,
        ];
    }

    private function answer(User $candidate, BrokerOffer $offer, string $status): void
    {
        if ($offer->candidate_id !== $candidate->id || $offer->status === BrokerOffer::WITHDRAWN) {
            throw new NotFoundHttpException;
        }

        if ($offer->status !== BrokerOffer::OPEN) {
            throw ValidationException::withMessages(['offer' => __('licence.offers.errors.answered')]);
        }

        $offer->forceFill(['status' => $status, 'answered_at' => now()])->save();
        $offer->load(['organisation', 'candidate']);
        Notification::send(
            $this->memberships->holdersIn(Role::Broker, $offer->organisation),
            new BrokerOfferNotice($offer, $status === BrokerOffer::ACCEPTED ? BrokerOfferNotice::ACCEPTED : BrokerOfferNotice::DECLINED),
        );
    }

    /**
     * The brokerage's rating: its mentors' 1-on-1 session ratings (A10.2a), shown after 24 hours.
     *
     * @return array{0: float|null, 1: int}
     */
    private function rating(Organisation $brokerage): array
    {
        $mentors = $this->memberships->holdersIn(Role::Mentor, $brokerage)->pluck('id')->all();
        $rated = SessionBooking::query()->whereIn('instructor_id', $mentors)->whereNotNull('rating')->where('rated_at', '<=', now()->subHours(SessionBookings::RATING_DELAY_HOURS));
        $count = (clone $rated)->count();
        $average = (clone $rated)->avg('rating');

        return [$count === 0 || ! is_numeric($average) ? null : round((float) $average, 1), $count];
    }

    /** @param OfferRow $row */
    private static function score(array $row): float
    {
        $terms = $row['split_percent'] / 100 * 30 - min(10, $row['monthly_fee_cents'] / 10000) + ($row['mentoring'] ? 8 : 0) + ($row['training'] !== null ? 4 : 0);

        return ($row['status'] === 'accepted' ? 1000 : 0) + ($row['fit'] ? 40 : 0) + ($row['rating'] ?? 3.5) * 6 + $terms;
    }
}
