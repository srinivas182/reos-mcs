<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Enums\OrganisationType;

/**
 * A tenant's plan (screens 59 and 60). Prices and what each includes come
 * from Plans and pricing (screen 67, B5.1); this names the plan.
 */
enum TenantPlan: string
{
    case Enterprise = 'enterprise';
    case Association = 'association';
    case Brokerage = 'brokerage';
    case School = 'school';
    case Partner = 'partner';
    /** Teams use their brokerage's plan. */
    case Inherits = 'inherits';

    public static function defaultFor(OrganisationType $type): self
    {
        return match ($type) {
            OrganisationType::Association => self::Association,
            OrganisationType::School => self::School,
            OrganisationType::ProviderBusiness => self::Partner,
            OrganisationType::Team => self::Inherits,
            OrganisationType::Brokerage, OrganisationType::Platform => self::Brokerage,
        };
    }
}
