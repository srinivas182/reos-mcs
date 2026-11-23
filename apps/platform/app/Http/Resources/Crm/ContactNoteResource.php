<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Crm\Sources;
use App\Models\ContactNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A note on a contact (B7.2): written in REOS or in a CRM.
 *
 * @property ContactNote $resource
 */
final class ContactNoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $note = $this->resource;
        $sources = app(Sources::class);

        return [
            'id' => $note->id,
            'body' => $note->body,
            'source' => $note->source,
            'source_name' => $sources->name($note->source),
            /**
             * The CRMs this REOS note was sent to.
             *
             * @var list<string>
             */
            'sent_to' => array_map(static fn (string $s): string => $sources->name($s), array_keys($note->pushed ?? [])),
            'at' => ($note->remote_updated_at ?? $note->created_at)?->toIso8601String(),
        ];
    }
}
