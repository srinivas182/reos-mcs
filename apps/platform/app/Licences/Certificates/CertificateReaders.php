<?php

declare(strict_types=1);

namespace App\Licences\Certificates;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Aws\Textract\TextractClient;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;

/**
 * Which reader reads certificates (B10.3b): AWS Textract with REOS's keys
 * from Settings › Integrations (`aws-textract`), the Simulator outside
 * production, or none (the person types the details).
 */
final readonly class CertificateReaders
{
    public const INTEGRATION = 'aws-textract';

    /** Tests bind a Guzzle handler here to answer for Textract. */
    public const HANDLER = 'reos.textract.handler';

    public function __construct(
        private Container $container,
        private Application $app,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    public function for(?string $fileName = null): ?CertificateReader
    {
        $definition = $this->catalogue->find(self::INTEGRATION);
        $mode = $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();

        if ($mode === IntegrationMode::Simulator) {
            return $this->app->isProduction() ? null : new SimulatedCertificateReader($fileName);
        }

        $environment = $mode->environment();

        if ($environment === null) {
            return null;
        }

        $keys = $this->vault->get(self::INTEGRATION, $environment);

        if (($keys['access_key_id'] ?? '') === '' || ($keys['secret_access_key'] ?? '') === '') {
            return null;
        }

        return new TextractCertificateReader(new TextractClient(array_filter([
            'region' => ($keys['region'] ?? '') !== '' ? $keys['region'] : 'us-east-2',
            'version' => 'latest',
            'credentials' => ['key' => $keys['access_key_id'], 'secret' => $keys['secret_access_key']],
            'http' => ['timeout' => 20, 'connect_timeout' => 5],
            'retries' => 1,
            'handler' => $this->container->bound(self::HANDLER) ? $this->container->make(self::HANDLER) : null,
        ], static fn (mixed $option): bool => $option !== null)));
    }
}
