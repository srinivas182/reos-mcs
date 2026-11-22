<?php

declare(strict_types=1);

namespace App\Content\Knowledge;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use RuntimeException;

/**
 * Amazon Titan Text Embeddings V2 on Bedrock (us-east-2): 1,024 numbers per
 * passage, normalised so cosine distance compares them. Only course content
 * is sent, never personal data.
 */
final readonly class TitanEmbedder implements Embedder
{
    /** Titan V2 reads at most 8,192 tokens; passages are far shorter, queries are cut. */
    private const MAX_CHARS = 20000;

    public function __construct(private BedrockRuntimeClient $client, private string $modelId) {}

    public function embed(string $text): array
    {
        $result = $this->client->invokeModel([
            'modelId' => $this->modelId,
            'contentType' => 'application/json',
            'accept' => 'application/json',
            'body' => json_encode([
                'inputText' => mb_substr($text, 0, self::MAX_CHARS),
                'dimensions' => self::DIMENSIONS,
                'normalize' => true,
            ], JSON_THROW_ON_ERROR),
        ]);

        $raw = $result->get('body');
        $body = json_decode(is_scalar($raw) || $raw instanceof \Stringable ? (string) $raw : '', true);
        $vector = is_array($body) ? ($body['embedding'] ?? null) : null;

        if (! is_array($vector) || count($vector) !== self::DIMENSIONS) {
            throw new RuntimeException('Bedrock returned no embedding.');
        }

        return array_values(array_map(static fn (mixed $value): float => is_numeric($value) ? (float) $value : 0.0, $vector));
    }
}
