<?php

declare(strict_types=1);

namespace App\Licences\Journey;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Sessions\SessionBookings;

/**
 * Your mentors (A10.3c, candidate f04): for a new agent, the mentors of
 * their own brokerage who take 1-on-1 bookings (A10.2a), with rating,
 * languages and next free hour. Exam instructors stay bookable for CE
 * questions from Book an instructor.
 *
 * @phpstan-import-type OfferRow from SessionBookings
 */
final readonly class Mentors
{
    public function __construct(private Memberships $memberships, private SessionBookings $bookings) {}

    /** @return array{brokerage: string|null, mentors: list<OfferRow>} */
    public function for(User $agent): array
    {
        foreach ($this->memberships->of($agent) as $membership) {
            if ($membership->role === Role::Agent && $membership->organisation->type === OrganisationType::Brokerage) {
                $mentors = [];

                foreach ($this->memberships->holdersIn(Role::Mentor, $membership->organisation) as $mentor) {
                    $mentors[] = $mentor->id;
                }

                return ['brokerage' => $membership->organisation->name, 'mentors' => $this->bookings->offers(null, $mentors)];
            }
        }

        return ['brokerage' => null, 'mentors' => []];
    }
}
