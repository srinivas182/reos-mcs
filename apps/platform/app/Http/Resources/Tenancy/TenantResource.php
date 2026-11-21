<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenancy;

use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Tenancy\TenantStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row on Tenants (screen 59).
 *
 * @property Organisation $resource
 */
final class TenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $tenant = $this->resource;
        $members = $tenant->getAttribute('members_count');

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'type' => $tenant->type->value,
            'parent' => $tenant->parent?->name,
            'states' => $tenant->states ?? [],
            'plan' => $tenant->effectivePlan()->value,
            'seats' => $tenant->tenantLimits()->seats,
            'members' => is_numeric($members) ? (int) $members : 0,
            'domain' => self::domain($tenant),
            'cell' => $tenant->cell,
            'status' => $tenant->status->value,
            'trial_days_left' => $tenant->status === TenantStatus::Trial && $tenant->trial_ends_at !== null
                ? max(0, (int) ceil(now()->diffInDays($tenant->trial_ends_at, false)))
                : null,
        ];
    }

    /** Where its people sign in: `<slug>.reos.com` until it verifies its own domain (A4.2). Teams use their brokerage's. */
    public static function domain(Organisation $tenant): ?string
    {
        if (in_array($tenant->type, [OrganisationType::Team, OrganisationType::ProviderBusiness], true)) {
            return null;
        }

        return $tenant->slug.'.'.config()->string('reos.tenancy.base_domain');
    }
}
