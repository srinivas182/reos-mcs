<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records who changed what, when and from where. Callers pass names of what
 * changed (field names, old and new modes), never secret values.
 */
final readonly class AuditLogger
{
    public function __construct(private Request $request, private Tenancy $tenancy) {}

    /**
     * @param  array<string, mixed>  $properties
     * @param  int|null  $organisationId  The tenant it belongs to; defaults to the current tenant (ADR 0015).
     */
    public function record(string $action, string $subjectType, string $subjectId, array $properties = [], ?User $actor = null, ?int $organisationId = null): AuditEvent
    {
        return AuditEvent::query()->create([
            'actor_id' => $actor?->id,
            'organisation_id' => $organisationId ?? $this->tenancy->tenant(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            'created_at' => now(),
        ]);
    }
}
