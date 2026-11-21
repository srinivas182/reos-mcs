<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Tenancy\TenantPlan;
use App\Tenancy\TenantStatus;

/**
 * The personas' organisations as tenants (A4.1b), matching the Tenants table
 * in the web journey (screen 59), plus a team and a brokerage on trial.
 */
final class DemoTenants
{
    /** Tenants added here, beyond the personas' own organisations. */
    public const EXTRA = ['Okoye Team', 'Bayou Realty'];

    public function seed(): void
    {
        $settings = [
            'Texas REALTORS®' => ['plan' => TenantPlan::Enterprise, 'cell' => 'cell-1', 'limits' => null],
            'Texas Realty School' => ['plan' => TenantPlan::School, 'cell' => 'cell-2', 'limits' => ['seats' => 40]],
            'Lone Star Realty' => ['plan' => TenantPlan::Brokerage, 'cell' => 'cell-1', 'limits' => ['seats' => 142, 'candidate_pool' => 5000]],
            'Katy Home Loans' => ['plan' => TenantPlan::Partner, 'cell' => 'cell-2', 'limits' => null],
        ];

        foreach ($settings as $name => $values) {
            Organisation::query()->where('name', $name)->update([
                'states' => json_encode(['TX']),
                'plan' => $values['plan']->value,
                'cell' => $values['cell'],
                'limits' => $values['limits'] === null ? null : json_encode($values['limits']),
            ]);
        }

        $loneStar = Organisation::query()->where('name', 'Lone Star Realty')->first();

        if ($loneStar !== null) {
            Organisation::query()->firstOrCreate(
                ['name' => 'Okoye Team', 'type' => OrganisationType::Team],
                ['parent_id' => $loneStar->id, 'states' => ['TX'], 'plan' => TenantPlan::Inherits, 'cell' => 'cell-1'],
            );
        }

        Organisation::query()->firstOrCreate(
            ['name' => 'Bayou Realty', 'type' => OrganisationType::Brokerage],
            ['states' => ['TX'], 'plan' => TenantPlan::Brokerage, 'cell' => 'cell-2', 'status' => TenantStatus::Trial,
                'trial_ends_at' => now()->addDays(14)->endOfDay(), 'limits' => ['seats' => 30]],
        );
    }
}
