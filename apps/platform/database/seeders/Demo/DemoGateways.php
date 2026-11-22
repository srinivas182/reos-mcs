<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Integrations\IntegrationMode;
use App\Models\GatewaySetting;
use App\Models\IntegrationSetting;

/**
 * Payment gateways as on screen 66 (B5.2a): Stripe the default for every
 * state and product with tenant accounts allowed; PayPal for candidate
 * packages and Authorize.net for Texas seat packs (association dues), both
 * switched off until their sandboxes are connected ("Coming soon").
 */
final readonly class DemoGateways
{
    public function seed(): void
    {
        GatewaySetting::query()->updateOrCreate(['gateway' => 'stripe'], ['states' => null, 'used_for' => null, 'is_default' => true, 'position' => 0, 'tenant_accounts' => true]);
        GatewaySetting::query()->updateOrCreate(['gateway' => 'paypal'], ['states' => null, 'used_for' => ['package'], 'is_default' => false, 'position' => 1, 'tenant_accounts' => false]);
        GatewaySetting::query()->updateOrCreate(['gateway' => 'authorize-net'], ['states' => ['TX'], 'used_for' => ['seat_pack'], 'is_default' => false, 'position' => 2, 'tenant_accounts' => true]);

        foreach (['paypal', 'authorize-net'] as $gateway) {
            IntegrationSetting::query()->firstOrCreate(['integration_id' => $gateway], ['mode' => IntegrationMode::Off]);
        }
    }
}
