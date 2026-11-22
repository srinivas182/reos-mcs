<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Messaging\Conversations;
use App\Models\Conversation;
use App\Models\User;

/**
 * Messages as at Lone Star Realty (B6.1): James Miller and his broker Linda
 * Park about an offer, and a huddle with his mentor Dana Brooks. Messages
 * is rolled out to Lone Star Realty's agents and brokers (DemoReleases).
 */
final readonly class DemoMessages
{
    public function __construct(private Conversations $conversations) {}

    public function seed(): void
    {
        $james = User::query()->where('email', 'james.miller@demo.reos.test')->first();
        $linda = User::query()->where('email', 'linda.park@demo.reos.test')->first();
        $dana = User::query()->where('email', 'dana.brooks@demo.reos.test')->first();

        if ($james === null || $linda === null || $dana === null || Conversation::query()->exists()) {
            return;
        }

        $offer = $this->conversations->start($james, [$linda->id], 'Can you look at the Elm St offer before I send it? The buyers want a 10-day option period.');
        $this->conversations->post($linda, $offer, 'On it. Ten days is fine for Elm St; add the survey date to the notes. Comments by 5.');
        $this->conversations->post($james, $offer, 'Thanks, Linda. Survey is booked for Tuesday.');

        $huddle = $this->conversations->start($dana, [$james->id, $linda->id], 'Friday huddle moves to 9:30 so we can cover the new CE rules.', 'Friday huddle');
        $this->conversations->post($linda, $huddle, 'Works for me. I will bring the TREC update.');
    }
}
