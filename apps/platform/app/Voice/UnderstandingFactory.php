<?php

declare(strict_types=1);

namespace App\Voice;

use App\Services\Ai\Bedrock;

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

    public function __construct(private Bedrock $bedrock) {}

    /** Claude, when it's set up; otherwise null. */
    public function ai(): ?ClaudeUnderstanding
    {
        $bedrock = $this->bedrock->client();

        return $bedrock === null ? null : new ClaudeUnderstanding($bedrock['client'], $bedrock['model']);
    }

    public function builtIn(): BuiltInUnderstanding
    {
        return new BuiltInUnderstanding;
    }
}
