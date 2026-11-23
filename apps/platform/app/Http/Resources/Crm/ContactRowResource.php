<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Crm\ContactHub;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A contact in the CRM hub's list (B8.3): the contact, its tab, the last
 * touch and the AI next step while it's fresh.
 *
 * @phpstan-import-type Row from ContactHub
 *
 * @property Row $resource
 */
final class ContactRowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            ...(new ContactResource($row['contact']))->toArray($request),
            /** @var 'new'|'active'|'past'|'sphere'|null */
            'segment' => $row['segment'],
            /**
             * The last note or finished task, from any source.
             *
             * @var string|null
             */
            'last_touch_at' => $row['last_touch_at'],
            /**
             * The AI next step, while the contact hasn't changed since.
             *
             * @var array{text: string, why: string, channel: string}|null
             */
            'next_step' => $row['insight'] === null ? null : ['text' => $row['insight']->next_step, 'why' => $row['insight']->why, 'channel' => $row['insight']->channel],
        ];
    }
}
