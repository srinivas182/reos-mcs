<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\IntegrationInterest;
use App\Models\ToolRequest;
use App\Models\User;

/** Demo "Notify me" taps and a tool request (B3.2), so the demand screens have data. */
final class DemoCatalogue
{
    public function seed(): void
    {
        $taps = [
            'james.miller' => ['zillow-leads', 'showingtime', 'open-to-close'],
            'linda.park' => ['zillow-leads', 'boldtrail-backoffice'],
            'dana.brooks' => ['zillow-leads', 'zoom'],
            'sofia.reyes' => ['phone-contacts'],
        ];

        foreach ($taps as $persona => $tools) {
            $user = User::query()->where('email', 'like', "{$persona}@%")->first();

            foreach ($tools as $tool) {
                if ($user !== null) {
                    IntegrationInterest::query()->firstOrCreate(['integration_id' => $tool, 'user_id' => $user->id, 'kind' => IntegrationInterest::NOTIFY]);
                }
            }
        }

        $james = User::query()->where('email', 'like', 'james.miller@%')->first();

        if ($james !== null) {
            ToolRequest::query()->firstOrCreate(
                ['user_id' => $james->id, 'name' => 'Sierra Interactive'],
                ['note' => 'Our team gets its leads there.'],
            );
        }
    }
}
