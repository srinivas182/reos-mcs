<?php

declare(strict_types=1);

namespace App\Crm\Insights;

/**
 * Writes a contact's summary and one next step (B8.3, ADR 0034): Claude on
 * Amazon Bedrock, or the simulator when Bedrock has no keys.
 *
 * @phpstan-type Brief array{
 *     agent: string,
 *     language: string,
 *     today: string,
 *     name: string,
 *     first_name: string,
 *     stage: string|null,
 *     segment: string|null,
 *     sources: list<string>,
 *     has_phone: bool,
 *     has_email: bool,
 *     days_since_touch: int|null,
 *     notes: list<array{at: string, source: string, text: string}>,
 *     tasks: list<array{title: string, due: string|null, done: bool}>,
 *     activity: list<array{at: string, text: string}>
 * }
 * @phpstan-type Insight array{summary: string, next_step: string, why: string, channel: string, draft: string|null}
 */
interface InsightWriter
{
    /** "ai" or "simulator", kept with the insight and in AI usage. */
    public function engine(): string;

    /**
     * @param  Brief  $brief
     * @return Insight
     */
    public function write(array $brief): array;
}
