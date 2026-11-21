<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kinds of organisation a role can belong to (A3.2). A4.1 adds the full
 * hierarchy, branding and domains.
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
}
