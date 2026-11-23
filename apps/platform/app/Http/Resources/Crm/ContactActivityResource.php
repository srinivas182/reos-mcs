<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Crm\Sources;
use App\Models\ContactActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of Sync activity (screen 23, B7.1), in plain English.
 *
 * @property ContactActivity $resource
 */
final class ContactActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $activity = $this->resource;
        $details = $activity->details ?? [];
        $sources = app(Sources::class);
        $field = $details['field'] ?? null;
        $previousSource = $details['previous_source'] ?? null;

        return [
            'id' => $activity->id,
            /** @var 'created'|'linked'|'changed'|'kept'|'removed'|'merged'|'split' */
            'kind' => $activity->kind,
            'contact' => ['id' => $activity->contact->uuid, 'name' => $activity->contact->name()],
            'source' => $activity->source,
            'source_name' => $sources->name($activity->source),
            /** What happened, in a sentence. */
            'text' => __('crm.activity.'.$activity->kind, [
                'name' => $activity->contact->name(),
                'source' => $sources->name($activity->source),
                'field' => $field === null ? '' : __('crm.fields.'.$field),
                'value' => $details['value'] ?? '',
                'previous' => $details['previous'] ?? '',
                'previousSource' => $previousSource === null ? 'REOS' : $sources->name($previousSource),
                'other' => $details['name'] ?? '',
            ]),
            'occurred_at' => $activity->occurred_at->toIso8601String(),
        ];
    }
}
