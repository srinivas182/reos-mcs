<?php

declare(strict_types=1);

namespace App\Licences\Journey;

use App\Authorization\Role;
use App\Billing\Ledger\Points;
use App\Mentoring\MentorDirectory;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Sessions\SessionBookings;

/**
 * Your mentors (A10.3c, candidate f04; A13.1a, agent screens 114 and
 * 116): the mentors of the agent's own brokerage who take 1-on-1 bookings
 * (A10.2a), with rating, languages and next free hour. The **assigned
 * mentor** comes first and is free; mentors only for assigned mentees are
 * left out for everyone else; `points` says whether points are accepted,
 * with the agent's balance. Mentors from other brokerages are never shown.
 *
 * @phpstan-import-type OfferRow from SessionBookings
 *
 * @phpstan-type MentorOffer array{id: int, user_id: int, name: string, school: string|null, bio: string|null, languages: list<string>, topics: list<string>, price_30: int, price_60: int, rating: float|null, ratings: int, next_slot: string|null, assigned: bool, free: bool, points: bool}
 */
final readonly class Mentors
{
    public function __construct(private Memberships $memberships, private SessionBookings $bookings, private MentorDirectory $directory, private Points $points) {}

    /** @return array{brokerage: string|null, mentors: list<MentorOffer>, assigned: int|null, change_requested: bool, points_balance: int} */
    public function for(User $agent): array
    {
        $brokerage = $this->directory->brokerageOf($agent);

        if ($brokerage === null) {
            return ['brokerage' => null, 'mentors' => [], 'assigned' => null, 'change_requested' => false, 'points_balance' => $this->points->balance($agent)];
        }

        $assignment = MentorDirectory::assignment($brokerage, $agent);
        $mentors = $this->memberships->holdersIn(Role::Mentor, $brokerage)->keyBy('id');
        $rows = [];

        foreach ($this->bookings->offers(null, array_values(array_map('intval', $mentors->keys()->all()))) as $offer) {
            $mentor = $mentors->get($offer['user_id']);

            if ($mentor === null) {
                continue;
            }

            $settings = MentorDirectory::settings($brokerage, $mentor);
            $assigned = $assignment?->mentor_id === $offer['user_id'];

            if (! $assigned && $settings->who === 'assigned') {
                continue;
            }

            $rows[] = [...$offer, 'assigned' => $assigned, 'free' => $assigned, 'points' => $settings->points];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['assigned'], $a['name']] <=> [$a['assigned'], $b['name']]);

        return ['brokerage' => $brokerage->name, 'mentors' => $rows, 'assigned' => $assignment?->mentor_id, 'change_requested' => $assignment?->change_requested_at !== null, 'points_balance' => $this->points->balance($agent)];
    }
}
