<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\TenantSso;
use App\Sso\SamlClient;
use App\Sso\SsoProtocol;
use App\Sso\SsoStatus;
use App\Sso\TestIdentityProvider;
use App\Tenancy\Tenancy;
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
            'Texas REALTORS®' => ['plan' => TenantPlan::Enterprise, 'cell' => 'cell-1', 'limits' => null,
                'brand' => ['primary' => '#1E3A8A', 'app_label' => 'Texas REALTORS® Learning', 'email_from_name' => 'Texas REALTORS®']],
            'Texas Realty School' => ['plan' => TenantPlan::School, 'cell' => 'cell-2', 'limits' => ['seats' => 40]],
            'Lone Star Realty' => ['plan' => TenantPlan::Brokerage, 'cell' => 'cell-1', 'limits' => ['seats' => 142, 'candidate_pool' => 5000],
                'brand' => ['primary' => '#1D4ED8', 'app_label' => 'Lone Star Realty Learning']],
            'Katy Home Loans' => ['plan' => TenantPlan::Partner, 'cell' => 'cell-2', 'limits' => null],
        ];

        foreach ($settings as $name => $values) {
            Organisation::query()->where('name', $name)->update([
                'states' => json_encode(['TX']),
                'plan' => $values['plan']->value,
                'cell' => $values['cell'],
                'limits' => $values['limits'] === null ? null : json_encode($values['limits']),
                // The journey's brands (screens 2 and 57).
                'brand' => isset($values['brand']) ? json_encode($values['brand']) : null,
            ]);
        }

        $loneStar = Organisation::query()->where('name', 'Lone Star Realty')->first();
        $texas = Organisation::query()->where('name', 'Texas REALTORS®')->first();

        // A member brokerage of Texas REALTORS® (journey screen 2: its sign-in
        // page offers the association's single sign-on).
        if ($loneStar !== null && $texas !== null) {
            $loneStar->forceFill(['parent_id' => $texas->id])->save();
        }

        // Verified with TREC (A15.1a): Linda Park's own broker license, which sponsors James and Dana.
        $loneStar?->forceFill(['trec_number' => '9012345'])->save();

        if ($loneStar !== null) {
            Organisation::query()->firstOrCreate(
                ['name' => 'Okoye Team', 'type' => OrganisationType::Team],
                ['parent_id' => $loneStar->id, 'states' => ['TX'], 'plan' => TenantPlan::Inherits, 'cell' => 'cell-1'],
            );
        }

        $this->singleSignOn();

        Organisation::query()->firstOrCreate(
            ['name' => 'Bayou Realty', 'type' => OrganisationType::Brokerage],
            ['states' => ['TX'], 'plan' => TenantPlan::Brokerage, 'cell' => 'cell-2', 'status' => TenantStatus::Trial,
                'trial_ends_at' => now()->addDays(14)->endOfDay(), 'limits' => ['seats' => 30]],
        );
    }

    /**
     * Texas REALTORS® signs its members in with the REOS test identity
     * provider (SAML), tested and on, so the button shows on its address and
     * its brokerages' (journey screens 2 and 57). Outside production only.
     */
    private function singleSignOn(): void
    {
        $texas = Organisation::query()->where('name', 'Texas REALTORS®')->first();
        $idp = app(TestIdentityProvider::class);

        if ($texas === null || ! $idp->available()) {
            return;
        }

        app(Tenancy::class)->run($texas->id, static fn (): TenantSso => TenantSso::query()->updateOrCreate(['organisation_id' => $texas->id], [
            'protocol' => SsoProtocol::Saml,
            'status' => SsoStatus::On,
            'settings' => [...app(SamlClient::class)->parseMetadata($idp->samlMetadata()), 'metadata_url' => null],
            'last_test_at' => now(),
            'last_test_passed' => true,
            'last_test_detail' => 'karen.webb@texasrealtors.example (Karen Webb)',
            'enabled_at' => now(),
        ]));
    }
}
