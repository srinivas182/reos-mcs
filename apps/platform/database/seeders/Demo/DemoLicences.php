<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\BrokerRole;
use App\Models\Licence;
use App\Models\State;
use App\Models\User;
use App\Services\Licences\LicenceCheck;
use App\Services\Licences\Licences;

/**
 * Demo licences (A5.1), as in the agent journey's "My licences" (screen 148):
 * James Miller's Texas sales agent licence, checked on the Simulator, and a
 * Louisiana one waiting for review (tracked for renewal only); Linda Park's
 * Texas broker licence, sponsoring her agents. Safe to run again.
 */
final readonly class DemoLicences
{
    public function __construct(private Licences $licences) {}

    public function seed(): void
    {
        $this->add('james.miller', 'TX', '0798765');
        $this->add('james.miller', 'LA', '0995-21837');
        $linda = $this->add('linda.park', 'TX', '9012345');

        if ($linda !== null) {
            $this->licences->setRole($linda[0], BrokerRole::SponsoringBroker, $linda[0]->user);
        }
    }

    /** @return array{0: Licence, 1: LicenceCheck, 2: string|null}|null */
    private function add(string $local, string $state, string $number): ?array
    {
        $user = User::query()->where('email', $local.'@'.Personas::DOMAIN)->first();

        if ($user === null || $user->licences()->where('state_code', $state)->exists()) {
            return null;
        }

        return $this->licences->add($user, State::query()->findOrFail($state), $number, null, null, askReview: true);
    }
}
