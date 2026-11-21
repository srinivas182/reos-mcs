<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kinds of organisation (tenant) a role can belong to (A3.2, A4.1).
 */
enum OrganisationType: string
{
    /** REOS itself: super admins, and people who joined on their own. */
    case Platform = 'platform';
    case Brokerage = 'brokerage';
    case School = 'school';
    case Association = 'association';
    /** A lender, inspector, title company or other service business. */
    case ProviderBusiness = 'provider_business';
    /** A team inside a brokerage (its parent); it inherits the brokerage's plan. */
    case Team = 'team';
}
