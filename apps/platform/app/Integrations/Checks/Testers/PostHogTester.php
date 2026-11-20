<?php

declare(strict_types=1);

namespace App\Integrations\Checks\Testers;

use App\Integrations\Checks\CheckResult;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use Illuminate\Http\Client\PendingRequest;

/** PostHog: asks for feature flags for a test visitor (records nothing). */
final class PostHogTester extends HttpTester
{
    public function test(IntegrationDefinition $definition, CredentialEnvironment $environment, array $credentials): CheckResult
    {
        $host = rtrim($credentials['host'] ?? '', '/');

        return $this->attempt(static fn (PendingRequest $request) => $request
            ->post("{$host}/decide/?v=3", [
                'api_key' => $credentials['project_key'] ?? '',
                'distinct_id' => 'reos-connection-check',
            ]));
    }
}
