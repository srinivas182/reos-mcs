<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Models\IntegrationCheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The latest "Test connection" or health-check result for one set of keys.
 *
 * @property IntegrationCheck $resource
 */
final class IntegrationCheckResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'outcome' => $this->resource->outcome->value,
            'message' => $this->resource->message,
            'parameters' => (object) $this->resource->parameters,
            'trigger' => $this->resource->trigger->value,
            'checked_by' => $this->resource->checker?->name,
            'duration_ms' => $this->resource->duration_ms,
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
