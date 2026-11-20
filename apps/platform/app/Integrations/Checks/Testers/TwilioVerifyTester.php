<?php

declare(strict_types=1);

namespace App\Integrations\Checks\Testers;

use App\Integrations\Checks\CheckResult;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use Illuminate\Http\Client\PendingRequest;

/** Twilio Verify: reads the Verify service record (sends nothing). */
final class TwilioVerifyTester extends HttpTester
{
    public function test(IntegrationDefinition $definition, CredentialEnvironment $environment, array $credentials): CheckResult
    {
        $service = $credentials['verify_service_sid'] ?? '';

        if (preg_match('/^VA[0-9a-f]{32}$/i', $service) !== 1) {
            return CheckResult::failed('invalidFormat', ['field' => 'verify_service_sid']);
        }

        return $this->attempt(static fn (PendingRequest $request) => $request
            ->withBasicAuth($credentials['account_sid'] ?? '', $credentials['auth_token'] ?? '')
            ->get("https://verify.twilio.com/v2/Services/{$service}"));
    }
}
