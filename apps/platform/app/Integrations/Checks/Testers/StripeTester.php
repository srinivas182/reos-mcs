<?php

declare(strict_types=1);

namespace App\Integrations\Checks\Testers;

use App\Integrations\Checks\CheckResult;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

/** Stripe and Stripe Connect: reads the account balance (read-only). */
final class StripeTester extends HttpTester
{
    public function test(IntegrationDefinition $definition, CredentialEnvironment $environment, array $credentials): CheckResult
    {
        $key = $credentials['secret_key'] ?? '';
        // Stripe marks test and live keys; catch keys pasted into the wrong box.
        $expected = $environment === CredentialEnvironment::Live ? '_live_' : '_test_';

        if (! Str::contains(Str::substr($key, 0, 10), $expected)) {
            return CheckResult::failed('wrongEnvironment');
        }

        return $this->attempt(static fn (PendingRequest $request) => $request
            ->withToken($key)
            ->get('https://api.stripe.com/v1/balance'));
    }
}
