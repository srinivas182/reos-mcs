<?php

declare(strict_types=1);

namespace App\Integrations\Checks\Testers;

use App\Integrations\Checks\CheckResult;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use Illuminate\Http\Client\PendingRequest;

/** Twilio: reads the account record (read-only). */
final class TwilioTester extends HttpTester
{
    public function test(IntegrationDefinition $definition, CredentialEnvironment $environment, array $credentials): CheckResult
    {
        $sid = $credentials['account_sid'] ?? '';

        if (preg_match('/^AC[0-9a-f]{32}$/i', $sid) !== 1) {
            return CheckResult::failed('invalidFormat', ['field' => 'account_sid']);
        }

        return $this->attempt(static fn (PendingRequest $request) => $request
            ->withBasicAuth($sid, $credentials['auth_token'] ?? '')
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}.json"));
    }
}
