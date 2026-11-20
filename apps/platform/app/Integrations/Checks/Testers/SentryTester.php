<?php

declare(strict_types=1);

namespace App\Integrations\Checks\Testers;

use App\Integrations\Checks\CheckResult;
use App\Integrations\Checks\ConnectionTester;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;

/**
 * Sentry: checks each DSN is well formed. Sentry has no read-only call for
 * DSN keys, and sending a test event would create a real issue, so this
 * check doesn't contact Sentry (2.4c's first real error proves the DSN).
 */
final class SentryTester implements ConnectionTester
{
    public function test(IntegrationDefinition $definition, CredentialEnvironment $environment, array $credentials): CheckResult
    {
        foreach (['dsn', 'browser_dsn', 'mobile_dsn'] as $field) {
            $dsn = $credentials[$field] ?? null;

            if ($dsn !== null && preg_match('#^https://[0-9a-f]{32}@[a-z0-9.\-]+(:\d+)?/\d+$#i', $dsn) !== 1) {
                return CheckResult::failed('invalidFormat', ['field' => $field]);
            }
        }

        return CheckResult::unverified('formatOnly');
    }

    public function contactsVendor(): bool
    {
        return false;
    }
}
