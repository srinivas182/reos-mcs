<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Gateways\Gateways;
use App\Integrations\Catalogue;
use App\Integrations\Checks\CheckOutcome;
use App\Integrations\IntegrationMode;
use App\Models\GatewaySetting;
use App\Models\Order;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Support\Facades\DB;

/**
 * Payment gateways (B5.2a, web journey screen 66): which gateway serves
 * which states and kinds of product, the default, tenant-owned accounts and
 * the kill switch. Keys and Off / Simulator / Sandbox / Live stay in
 * Settings › Integrations; this is how checkout uses them. Every change is
 * audited with before and after.
 */
final readonly class GatewaySettings
{
    /** What each gateway can do; screen 66 shows it and routing respects it. */
    public const CAPABILITIES = [
        Gateways::STRIPE => ['recurring' => true, 'tax' => true, 'payouts' => true, 'tenant_accounts' => true],
        Gateways::PAYPAL => ['recurring' => false, 'tax' => false, 'payouts' => false, 'tenant_accounts' => false],
        // Associations and brokerages that already bank through Authorize.net can use their own account.
        Gateways::AUTHORIZE_NET => ['recurring' => false, 'tax' => false, 'payouts' => false, 'tenant_accounts' => true],
    ];

    public function __construct(
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private Gateways $gateways,
        private AuditLogger $audit,
    ) {}

    public function for(string $gateway): GatewaySetting
    {
        $setting = GatewaySetting::query()->find($gateway);

        return $setting ?? new GatewaySetting([
            'gateway' => $gateway,
            'states' => null,
            'used_for' => null,
            // Until screen 66 is saved, Stripe is the default for everything.
            'is_default' => $gateway === Gateways::STRIPE && ! GatewaySetting::query()->where('is_default', true)->exists(),
            'position' => array_search($gateway, Gateways::CHECKOUT, true) ?: 0,
            'tenant_accounts' => false,
            'paused' => false,
        ]);
    }

    /**
     * The gateway table: settings, how each runs, health, and today's orders.
     *
     * @return list<array<string, mixed>>
     */
    public function overview(): array
    {
        $since = now()->subDay();
        $orders = Order::query()->where('created_at', '>=', $since)
            ->select('gateway', DB::raw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid"), DB::raw("SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired"))
            ->groupBy('gateway')->get()->keyBy('gateway');

        $rows = [];

        foreach (Gateways::CHECKOUT as $id) {
            $definition = $this->catalogue->find($id);

            if ($definition === null) {
                continue;
            }

            $setting = $this->for($id);
            $status = $this->hub->status($definition);
            $mode = $status->effectiveMode();
            $environment = $mode->environment();
            $check = $environment === null ? null : $this->hub->latestCheck($definition, $environment);
            $counts = $orders->get($id);

            $rows[] = [
                'id' => $id,
                'name' => __('gateways.names.'.$id),
                'mode' => $status->mode->value,
                'running' => $this->gateways->driver($id) !== null,
                'is_default' => $setting->is_default,
                'position' => $setting->position,
                'states' => $setting->states,
                'used_for' => $setting->used_for,
                'tenant_accounts' => $setting->tenant_accounts,
                'paused' => $setting->paused,
                'paused_reason' => $setting->paused_reason,
                'capabilities' => self::CAPABILITIES[$id],
                'health' => match (true) {
                    $status->pausedAt !== null => 'autoPaused',
                    $mode === IntegrationMode::Off => null,
                    $mode === IntegrationMode::Simulator => 'simulator',
                    $check?->outcome === CheckOutcome::Failed => 'failing',
                    default => 'healthy',
                },
                'orders_today' => ['paid' => (int) ($counts->paid ?? 0), 'expired' => (int) ($counts->expired ?? 0)],
            ];
        }

        return $rows;
    }

    /**
     * Saves how a gateway is used. Making one the default clears the others.
     *
     * @param  array{states: list<string>|null, used_for: list<string>|null, is_default: bool, tenant_accounts: bool}  $input
     */
    public function save(string $gateway, array $input, User $actor): GatewaySetting
    {
        return DB::transaction(function () use ($gateway, $input, $actor): GatewaySetting {
            $setting = $this->for($gateway);
            $before = self::snapshot($setting);

            if ($input['is_default']) {
                GatewaySetting::query()->where('gateway', '!=', $gateway)->where('is_default', true)->update(['is_default' => false]);
            }

            $setting->fill([
                'states' => $input['states'] === null ? null : array_values(array_unique($input['states'])),
                'used_for' => $input['used_for'] === null ? null : array_values(array_unique($input['used_for'])),
                'is_default' => $input['is_default'],
                // Only gateways a tenant can connect its own account to (Stripe Connect, Authorize.net).
                'tenant_accounts' => $input['tenant_accounts'] && self::CAPABILITIES[$gateway]['tenant_accounts'],
                'updated_by' => $actor->id,
            ])->save();

            $after = self::snapshot($setting);

            if ($before !== $after) {
                $this->audit->record('gateway.updated', 'gateway', $gateway, ['before' => $before, 'after' => $after], $actor);
            }

            return $setting;
        });
    }

    /** The kill switch: checkout moves to the next allowed gateway, or says purchases are paused. */
    public function pause(string $gateway, string $reason, User $actor): void
    {
        $setting = $this->for($gateway);

        if ($setting->paused) {
            return;
        }

        $setting->fill(['paused' => true, 'paused_reason' => $reason, 'updated_by' => $actor->id])->save();
        $this->audit->record('gateway.paused', 'gateway', $gateway, ['from' => 'running', 'to' => 'paused', 'reason' => $reason], $actor);
    }

    public function resume(string $gateway, User $actor): void
    {
        $setting = $this->for($gateway);

        if (! $setting->paused) {
            return;
        }

        $setting->fill(['paused' => false, 'paused_reason' => null, 'updated_by' => $actor->id])->save();
        $this->audit->record('gateway.resumed', 'gateway', $gateway, ['from' => 'paused', 'to' => 'running'], $actor);
    }

    /** @return array<string, mixed> */
    private static function snapshot(GatewaySetting $setting): array
    {
        return [
            'states' => $setting->states,
            'used_for' => $setting->used_for,
            'is_default' => $setting->is_default,
            'tenant_accounts' => $setting->tenant_accounts,
        ];
    }
}
