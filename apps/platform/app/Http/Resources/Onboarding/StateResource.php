<?php

declare(strict_types=1);

namespace App\Http\Resources\Onboarding;

use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A state on "Where do you want to be licensed?".
 *
 * @property State $resource
 */
final class StateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => (string) $this->resource->code,
            'name' => (string) $this->resource->name,
            'status' => $this->resource->status,
            /** People can register and study for this state now; otherwise "Coming soon" with a waitlist. */
            'live' => (bool) $this->resource->status->isLive(),
        ];
    }
}
