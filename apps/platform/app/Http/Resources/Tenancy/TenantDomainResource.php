<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenancy;

use App\Domains\HostnameState;
use App\Models\TenantDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tenant's own domain on screens 41 and 57: the records to add and
 * whether each is in place.
 *
 * @property TenantDomain $resource
 */
final class TenantDomainResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $domain = $this->resource;

        return [
            'hostname' => $domain->hostname,
            'status' => $domain->status,
            'ssl' => $domain->ssl,
            'live' => $domain->live(),
            // The CNAME and ownership TXT are proven when the domain is active; SSL's own records when SSL is.
            'records' => array_map(static fn (array $record, int $i): array => [
                ...$record,
                'verified' => $i < 2 ? $domain->status === HostnameState::ACTIVE : $domain->ssl === HostnameState::ACTIVE,
            ], $domain->records, array_keys($domain->records)),
            'error' => $domain->last_error,
            'last_checked_at' => $domain->last_checked_at?->toIso8601String(),
            'verified_at' => $domain->verified_at?->toIso8601String(),
        ];
    }
}
