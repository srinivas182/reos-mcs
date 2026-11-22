<?php

declare(strict_types=1);

namespace App\Content\Knowledge;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * Picks the embedder from how "Amazon Titan Text Embeddings V2 on Bedrock"
 * runs in Settings › Integrations. Off and the Simulator have none: the
 * knowledge base then searches by keyword only. Meaning search also needs
 * PostgreSQL with pgvector (every environment but SQLite tests).
 */
final readonly class Embedders
{
    public const INTEGRATION = 'aws-bedrock-embeddings';

    public const DEFAULT_MODEL = 'amazon.titan-embed-text-v2:0';

    /** Tests bind a Guzzle-style handler here to answer for Bedrock. */
    public const HANDLER = 'reos.bedrock.embeddings.handler';

    public function __construct(
        private Container $container,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    public function embedder(): ?Embedder
    {
        if (! self::vectors()) {
            return null;
        }

        $definition = $this->catalogue->find(self::INTEGRATION);
        $environment = ($definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode())->environment();

        if ($environment === null) {
            return null;
        }

        $keys = $this->vault->get(self::INTEGRATION, $environment);
        $value = static fn (string $key): string => is_string($keys[$key] ?? null) ? trim($keys[$key]) : '';

        if ($value('access_key_id') === '' || $value('secret_access_key') === '') {
            return null;
        }

        $client = new BedrockRuntimeClient(array_filter([
            'region' => $value('region') !== '' ? $value('region') : 'us-east-2',
            'version' => 'latest',
            'credentials' => ['key' => $value('access_key_id'), 'secret' => $value('secret_access_key')],
            'http' => ['timeout' => 15, 'connect_timeout' => 5],
            'retries' => 2,
            'handler' => $this->container->bound(self::HANDLER) ? $this->container->make(self::HANDLER) : null,
        ], static fn (mixed $option): bool => $option !== null));

        return new TitanEmbedder($client, $value('model_id') !== '' ? $value('model_id') : self::DEFAULT_MODEL);
    }

    /** Whether passages have an embedding column (PostgreSQL with pgvector). */
    public static function vectors(): bool
    {
        static $present = [];
        $connection = DB::connection();
        $key = $connection->getName().'|'.$connection->getDatabaseName();

        if (! isset($present[$key])) {
            $present[$key] = $connection->getDriverName() === 'pgsql'
                && $connection->selectOne("SELECT 1 FROM information_schema.columns WHERE table_name = 'kb_passages' AND column_name = 'embedding'") !== null;
        }

        return $present[$key];
    }

    /**
     * A vector as pgvector's text form.
     *
     * @param  array<mixed>  $vector
     */
    public static function literal(array $vector): string
    {
        return '['.implode(',', array_map(static fn (mixed $v): string => is_numeric($v) ? (string) (float) $v : '0', $vector)).']';
    }
}
