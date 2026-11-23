<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Mls\MlsFeeds;
use App\Mls\MlsSync;
use App\Models\MlsMember;
use App\Models\MlsMemberLink;
use App\Models\User;

/**
 * My listings (B10.1): the MLS Simulator's sample listings, with James
 * Miller and Dana Brooks linked to their HAR member records (found by their
 * TREC licences), as in the agent journey. Safe to run again.
 */
final readonly class DemoListings
{
    public function __construct(private MlsFeeds $feeds, private MlsSync $sync) {}

    public function seed(): void
    {
        foreach ($this->feeds->all() as $definition) {
            $this->sync->run($definition);
        }

        foreach (['james.miller' => 'HAR-M1', 'dana.brooks' => 'HAR-M2'] as $local => $memberKey) {
            $user = User::query()->where('email', $local.'@'.Personas::DOMAIN)->first();
            $member = MlsMember::query()->where('integration', 'mls-har')->where('member_key', $memberKey)->first();

            if ($user !== null && $member !== null) {
                MlsMemberLink::query()->updateOrCreate(
                    ['user_id' => $user->id, 'integration' => $member->integration, 'member_key' => $member->member_key],
                    ['status' => MlsMemberLink::CONFIRMED],
                );
            }
        }
    }
}
