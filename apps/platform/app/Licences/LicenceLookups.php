<?php

declare(strict_types=1);

namespace App\Licences;

use App\Integrations\Catalogue;
use App\Integrations\IntegrationMode;
use App\Models\RegulatorRecord;
use App\Models\State;
use App\Services\Integrations\IntegrationHub;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;

/**
 * Which regulator lookup checks a state's licences (A5.1). Each state with a
 * lookup has an integration in the register (Texas: `trec-licence`, built in
 * B10.3). Live, TREC's imported data answers; on the Simulator (outside
 * production) demo records answer; with no lookup, a new licence goes to a
 * reviewer.
 */
final readonly class LicenceLookups
{
    /** @var array<string, string> state code => integration id */
    public const INTEGRATIONS = ['TX' => 'trec-licence'];

    public function __construct(private Application $app, private Catalogue $catalogue, private IntegrationHub $hub) {}

    public function for(State $state): ?LicenceLookup
    {
        $integration = self::INTEGRATIONS[$state->code] ?? null;
        $definition = $integration === null ? null : $this->catalogue->find($integration);

        if ($definition === null) {
            return null;
        }

        $mode = $this->hub->status($definition)->effectiveMode();

        return match (true) {
            $mode === IntegrationMode::Simulator => $this->app->isProduction() ? null : new SimulatedLicenceLookup(CarbonImmutable::now()),
            // TREC's data, imported nightly (B10.3); until the first import, a reviewer checks.
            $mode !== IntegrationMode::Off && RegulatorRecord::query()->where('authority', 'TREC')->exists() => new TrecLicenceLookup,
            default => null,
        };
    }
}
