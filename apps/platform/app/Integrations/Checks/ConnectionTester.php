<?php

declare(strict_types=1);

namespace App\Integrations\Checks;

use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;

/**
 * Checks one vendor's keys with the cheapest harmless request it offers
 * (read the account, or ask for a token with a dummy code). A tester never
 * changes anything at the vendor and never returns response bodies.
 */
interface ConnectionTester
{
    /**
     * @param  array<string, string>  $credentials  saved keys for this environment
     */
    public function test(IntegrationDefinition $definition, CredentialEnvironment $environment, array $credentials): CheckResult;

    /** Whether this tester really asks the vendor (scheduled health checks skip the rest). */
    public function contactsVendor(): bool;
}
