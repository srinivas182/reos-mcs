<?php

declare(strict_types=1);

namespace App\Integrations\Checks\Testers;

use App\Integrations\Checks\CheckResult;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use Illuminate\Http\Client\PendingRequest;

/** Grafana Cloud OTLP: sends an empty batch of traces (stores nothing). */
final class GrafanaOtlpTester extends HttpTester
{
    public function test(IntegrationDefinition $definition, CredentialEnvironment $environment, array $credentials): CheckResult
    {
        $endpoint = rtrim($credentials['otlp_endpoint'] ?? '', '/');

        return $this->attempt(static fn (PendingRequest $request) => $request
            ->withBasicAuth($credentials['instance_id'] ?? '', $credentials['access_token'] ?? '')
            ->post("{$endpoint}/v1/traces", ['resourceSpans' => []]));
    }
}
