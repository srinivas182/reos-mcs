<?php

declare(strict_types=1);

namespace App\ProviderNetwork;

/**
 * The provider network's rules (B15.2, provider screen 6): accepted with the
 * date and version, and asked again whenever the version changes. RESPA
 * Section 8 shapes the first two: providers pay REOS a flat fee for
 * listing and advertising, never for business, and never pay agents.
 */
final class NetworkRules
{
    /** Change this when a rule's wording changes: every provider is asked again. */
    public const VERSION = '2026-11-30';

    public const RULES = ['flat_fee', 'no_payments', 'consent', 'advertising', 'no_spam'];
}
