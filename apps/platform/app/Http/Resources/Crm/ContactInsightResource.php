<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\ContactInsight;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A contact's AI summary and next step (B8.3): free for agents, kept until
 * the contact changes.
 *
 * @property ContactInsight $resource
 */
final class ContactInsightResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $insight = $this->resource;

        return [
            'summary' => $insight->summary,
            /** One action, starting with a verb. */
            'next_step' => $insight->next_step,
            /** Why, from the record: "No touch in 21 days; they asked about Katy schools". */
            'why' => $insight->why,
            /** @var 'call'|'text'|'email'|'note' */
            'channel' => $insight->channel,
            /** A short text in the agent's voice, sent from their own phone; never sent by REOS. */
            'draft' => $insight->draft,
            /** @var 'ai'|'simulator' */
            'engine' => $insight->engine,
            'generated_at' => $insight->generated_at->toIso8601String(),
        ];
    }
}
