<?php

declare(strict_types=1);

namespace App\Crm\Insights;

use App\Services\Ai\Bedrock;

/** Claude when "Claude on Amazon Bedrock" has test or live keys; otherwise the simulator. */
final readonly class InsightWriters
{
    public function __construct(private Bedrock $bedrock) {}

    public function writer(): InsightWriter
    {
        $bedrock = $this->bedrock->client(30);

        return $bedrock === null ? new SimulatedInsightWriter : new ClaudeInsightWriter($bedrock['client'], $bedrock['model']);
    }
}
