<?php

declare(strict_types=1);

namespace App\Voice;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Contracts\Container\Container;

/**
 * Picks how speech is understood from how "Claude on Amazon Bedrock" runs in
 * Settings › Integrations: test or live keys use Claude; Off and the
 * Simulator use the built-in parser (demo data needs no AI).
 */
final readonly class UnderstandingFactory
{
    public const INTEGRATION = 'aws-bedrock-claude';

    /** Tests bind a Guzzle-style handler here to answer for Bedrock. */
    public const HANDLER = 'reos.bedrock.handler';

    public function __construct(
        private Container $container,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    /** Claude, when it's set up; otherwise null. */
    public function ai(): ?ClaudeUnderstanding
    {
        $definition = $this->catalogue->find(self::INTEGRATION);
        $mode = $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();
        $environment = $mode->environment();

        if ($environment === null) {
            return null;
        }

        $keys = $this->vault->get(self::INTEGRATION, $environment);
        $value = static fn (string $key): string => is_string($keys[$key] ?? null) ? $keys[$key] : '';

        if ($value('access_key_id') === '' || $value('secret_access_key') === '' || $value('model_id') === '') {
            return null;
        }

        $client = new BedrockRuntimeClient(array_filter([
            'region' => $value('region') !== '' ? $value('region') : 'us-east-2',
            'version' => 'latest',
            'credentials' => ['key' => $value('access_key_id'), 'secret' => $value('secret_access_key')],
            'http' => ['timeout' => 15, 'connect_timeout' => 5],
            'retries' => 1,
            'handler' => $this->container->bound(self::HANDLER) ? $this->container->make(self::HANDLER) : null,
        ], static fn (mixed $option): bool => $option !== null));

        return new ClaudeUnderstanding($client, $value('model_id'));
    }

    public function builtIn(): BuiltInUnderstanding
    {
        return new BuiltInUnderstanding;
    }
}
