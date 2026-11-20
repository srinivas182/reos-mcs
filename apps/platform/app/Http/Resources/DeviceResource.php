<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One signed-in device in "Your devices".
 *
 * @property PersonalAccessToken $resource
 */
final class DeviceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $current = $request->user()?->currentAccessToken();

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            /** ios, android or web. */
            'platform' => $this->resource->platform,
            'app_version' => $this->resource->app_version,
            'last_used_at' => $this->resource->last_used_at?->toIso8601String(),
            'signed_in_at' => $this->resource->created_at?->toIso8601String(),
            /** The device making this request. */
            'current' => $current instanceof PersonalAccessToken && $current->id === $this->resource->id,
        ];
    }
}
