<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use App\Voice\UnderstandingFactory;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Contracts\Container\Container;

/**
 * Claude on Amazon Bedrock, as set in Settings › Integrations (the model is
 * chosen on screen 69's integration): a client when test or live keys are
 * saved, otherwise null (Off and the Simulator use REOS's built-in fallbacks).
 * Shared by voice to-dos (B4.3) and AI lessons (A7.3).
 */
final readonly class Bedrock
{
    public const INTEGRATION = 'aws-bedrock-claude';

    public function __construct(
        private Container $container,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    /** @return array{client: BedrockRuntimeClient, model: string}|null */
    public function client(int $timeout = 15): ?array
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
            'http' => ['timeout' => $timeout, 'connect_timeout' => 5],
            'retries' => 1,
            'handler' => $this->container->bound(UnderstandingFactory::HANDLER) ? $this->container->make(UnderstandingFactory::HANDLER) : null,
        ], static fn (mixed $option): bool => $option !== null));

        return ['client' => $client, 'model' => $value('model_id')];
    }

    /**
     * The input Claude gave the forced tool, or null when it answered without it.
     *
     * @param  array<array-key, mixed>|\ArrayAccess<array-key, mixed>  $result
     * @return array<array-key, mixed>|null
     */
    public static function toolInput(mixed $result, string $tool): ?array
    {
        $output = $result['output'] ?? null;
        $message = is_array($output) ? ($output['message'] ?? null) : null;
        $content = is_array($message) ? ($message['content'] ?? null) : null;

        foreach (is_array($content) ? $content : [] as $block) {
            $use = is_array($block) ? ($block['toolUse'] ?? null) : null;

            if (is_array($use) && ($use['name'] ?? null) === $tool && is_array($use['input'] ?? null)) {
                return $use['input'];
            }
        }

        return null;
    }
}
