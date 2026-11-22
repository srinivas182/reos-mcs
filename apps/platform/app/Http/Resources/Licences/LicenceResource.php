<?php

declare(strict_types=1);

namespace App\Http\Resources\Licences;

use App\Enums\StateStatus;
use App\Models\Licence;
use App\Services\StateRules\CeRequirements;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One state licence (agent journey screen 148).
 *
 * @property Licence $resource
 */
final class LicenceResource extends JsonResource
{
    /** @return array<string, mixed>|null */
    private function ce(): ?array
    {
        return $this->resource->status->counts() ? app(CeRequirements::class)->for($this->resource) : null;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $licence = $this->resource;
        $state = $licence->state;

        return [
            'id' => $licence->id,
            /**
             * @var array{code: string, name: string, live: bool}
             */
            'state' => ['code' => $state->code, 'name' => $state->name, 'live' => $state->status === StateStatus::Live],
            /** @var 'sales_agent'|'broker' */
            'type' => $licence->type->value,
            'number' => $licence->number,
            /** @var 'active'|'inactive'|'expired'|'pending_review'|'rejected' */
            'status' => $licence->status->value,
            /** @var 'sponsoring_broker'|'designated_broker'|'delegated_supervisor'|null */
            'broker_role' => $licence->broker_role?->value,
            /** The name on the regulator's record. */
            'holder_name' => $licence->holder_name,
            'licensed_on' => $licence->licensed_on?->toDateString(),
            /** The renewal date. */
            'expires_on' => $licence->expires_on?->toDateString(),
            'sponsor_name' => $licence->sponsor_name,
            /** @var bool */
            'first_renewal' => $licence->first_renewal,
            /** `simulator`, `trec-licence` or `reviewer`; null while waiting for review. */
            'verified_by' => $licence->verified_by,
            'verified_at' => $licence->verified_at?->toIso8601String(),
            /** Why it's waiting for review, or why it was rejected. */
            'review_note' => $licence->review_note,
            /**
             * The state isn't live yet: tracked for renewal only.
             *
             * @var bool
             */
            'tracked_only' => $state->status !== StateStatus::Live,
            /**
             * CE this period ("9 of 18 h") and what's still needed, from the
             * state's rules in force today (A5.2); null while waiting for
             * review, or when the state has no CE rules.
             *
             * @var array{state: string, due_on: string|null, period_start: string|null, days_left: int|null, first_renewal: bool, hours_required: float, hours_done: float, hours_left: float, complete: bool, parts: list<array{code: string, title: string, kind: 'mandatory'|'broker_responsibility'|'qualifying'|'elective', hours: float, hours_done: float, done: bool}>, deferral: array{fee_usd: float, days: int}|null, sources: list<array{key: string, citation: string, url: string|null, effective_from: string}>}|null
             */
            'ce' => $this->ce(),
        ];
    }
}
