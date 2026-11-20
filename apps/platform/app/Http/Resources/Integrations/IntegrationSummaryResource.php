<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Integrations\IntegrationDefinition;
use App\Integrations\IntegrationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row on Settings › Integrations.
 */
final class IntegrationSummaryResource extends JsonResource
{
    public function __construct(
        private readonly IntegrationDefinition $definition,
        private readonly IntegrationStatus $status,
    ) {
        parent::__construct($definition);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->definition->id,
            'name' => $this->definition->name,
            'purpose' => $this->definition->purpose,
            'kind' => $this->definition->kind,
            'category' => $this->definition->category,
            'approval' => $this->definition->accessStatus,
            'personal_data' => $this->definition->personalData,
            'needs_keys' => $this->definition->needsKeys(),
            'mode' => $this->status->mode->value,
            'paused_at' => $this->status->pausedAt?->toIso8601String(),
            'keys' => [
                'sandbox' => $this->status->hasSandboxKeys,
                'live' => $this->status->hasLiveKeys,
            ],
        ];
    }
}
