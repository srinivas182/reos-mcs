<?php

declare(strict_types=1);

namespace App\Mls;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationDefinition;
use App\Integrations\IntegrationMode;
use App\Mls\Reso\ResoSource;
use App\Mls\Reso\ResoWebApi;
use App\Practice\StudyPlans;
use App\Services\Integrations\IntegrationHub;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;

/**
 * The MLS feeds REOS replicates (B10.1, ADR 0042): every register entry with
 * an `mls` block. Each runs as the super admin set it in Settings ›
 * Integrations: the Simulator's demo listings (outside production), or the
 * vendor's RESO Web API with REOS's keys. No vendor list lives in code.
 */
final readonly class MlsFeeds
{
    public function __construct(
        private Application $app,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
        private Factory $http,
        private Cache $cache,
    ) {}

    /** @return list<IntegrationDefinition> */
    public function all(): array
    {
        return array_values(array_filter($this->catalogue->all(), static fn (IntegrationDefinition $d): bool => $d->mls !== null));
    }

    public function find(string $integration): ?IntegrationDefinition
    {
        $definition = $this->catalogue->find($integration);

        return $definition?->mls === null ? null : $definition;
    }

    /** Where this MLS's records come from now, or null while it's off (or has no keys). */
    public function source(IntegrationDefinition $definition): ?ResoSource
    {
        $mls = $definition->mls;

        if ($mls === null) {
            return null;
        }

        $mode = $this->hub->status($definition)->effectiveMode();

        if ($mode === IntegrationMode::Simulator) {
            return $this->app->isProduction() ? null : new SimulatedMls($mls->dataset, CarbonImmutable::now(StudyPlans::TIMEZONE)->startOfDay());
        }

        $environment = $mode->environment();

        if ($environment === null || $mls->vendor === MlsVendor::ToConfirm) {
            return null;
        }

        $credentials = $this->vault->get($definition->id, $environment);

        if (($credentials['server_url'] ?? '') === '') {
            $credentials['server_url'] = $definition->field('server_url')->default ?? '';
        }

        return new ResoWebApi($this->http, $this->cache, $definition->id, $mls, $credentials);
    }

    /** Whether agents should see "Sample data" beside this MLS's listings. */
    public function isSample(string $integration): bool
    {
        $definition = $this->find($integration);

        return $definition !== null && $this->hub->status($definition)->effectiveMode() === IntegrationMode::Simulator;
    }
}
