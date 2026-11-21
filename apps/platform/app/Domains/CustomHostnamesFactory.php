<?php

declare(strict_types=1);

namespace App\Domains;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/** Picks how custom domains run from Settings › Integrations (Cloudflare for SaaS). */
final readonly class CustomHostnamesFactory
{
    public const INTEGRATION = 'cloudflare-saas';

    public const DEFAULT_TARGET = 'tenants.reos.com';

    public function __construct(
        private Container $container,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    public function make(): CustomHostnames
    {
        $definition = $this->catalogue->find(self::INTEGRATION);
        $mode = $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();

        if ($mode === IntegrationMode::Simulator && ! $this->container->make('app')->isProduction()) {
            return new SimulatorCustomHostnames(self::DEFAULT_TARGET);
        }

        $environment = $mode->environment() ?? throw new RuntimeException('Custom domains are switched off in Settings › Integrations.');
        $keys = $this->vault->get(self::INTEGRATION, $environment);

        return new CloudflareCustomHostnames(
            $this->container->make(Http::class),
            (string) ($keys['api_token'] ?? ''),
            (string) ($keys['zone_id'] ?? ''),
            (string) (($keys['cname_target'] ?? '') ?: self::DEFAULT_TARGET),
        );
    }
}
