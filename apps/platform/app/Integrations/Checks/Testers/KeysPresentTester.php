<?php

declare(strict_types=1);

namespace App\Integrations\Checks\Testers;

use App\Integrations\Checks\CheckResult;
use App\Integrations\Checks\ConnectionTester;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;

/**
 * Used until a vendor has its own tester (built with that integration's
 * sprint): the keys are complete, but nothing is sent to the vendor.
 */
final class KeysPresentTester implements ConnectionTester
{
    public function test(IntegrationDefinition $definition, CredentialEnvironment $environment, array $credentials): CheckResult
    {
        if (! $definition->needsKeys()) {
            return CheckResult::unverified('publicData');
        }

        return CheckResult::unverified('noAutomaticCheck', ['sprint' => $definition->buildSprint]);
    }

    public function contactsVendor(): bool
    {
        return false;
    }
}
