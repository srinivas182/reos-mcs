<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\BrokerRole;
use App\Models\CeCompletion;
use App\Models\Licence;
use App\Models\State;
use App\Models\User;
use App\Services\Licences\LicenceCheck;
use App\Services\Licences\Licences;

/**
 * Demo licences (A5.1), as in the agent journey's "My licences" (screen 148):
 * James Miller's Texas sales agent licence, checked on the Simulator, and a
 * Louisiana one waiting for review (tracked for renewal only); Linda Park's
 * Texas broker licence, sponsoring her agents. James has 9 of 18 CE hours.
 * Safe to run again.
 */
final readonly class DemoLicences
{
    public function __construct(private Licences $licences) {}

    public function seed(): void
    {
        $this->add('james.miller', 'TX', '0798765');
        $this->completions('james.miller');
        $this->add('james.miller', 'LA', '0995-21837');
        $linda = $this->add('linda.park', 'TX', '9012345');

        if ($linda !== null) {
            $this->licences->setRole($linda[0], BrokerRole::SponsoringBroker, $linda[0]->user);
        }
    }

    /** James's CE so far: 9 of 18 hours, Legal Update II and electives to go (journey screen 148). */
    private function completions(string $local): void
    {
        $user = User::query()->where('email', $local.'@'.Personas::DOMAIN)->first();

        if ($user === null || CeCompletion::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        foreach ([
            ['Legal Update I: Laws, Rules and Forms', 'legal_update_1', 4, '2026-02-10'],
            ['Real estate contracts', 'contracts', 3, '2026-05-18'],
            ['Negotiating offers in a shifting market', null, 2, '2026-09-03'],
        ] as [$title, $code, $hours, $on]) {
            CeCompletion::query()->create(['user_id' => $user->id, 'state_code' => 'TX', 'title' => $title, 'course_code' => $code, 'hours' => $hours, 'provider' => 'REOS', 'source' => 'reos', 'completed_on' => $on]);
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
