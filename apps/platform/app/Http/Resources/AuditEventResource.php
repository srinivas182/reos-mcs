<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry in a "Recent changes" list.
 *
 * @property AuditEvent $resource
 */
final class AuditEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'action' => $this->resource->action,
            'actor' => $this->resource->actor?->name,
            'properties' => $this->resource->properties,
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
