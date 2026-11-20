<?php

declare(strict_types=1);

namespace App\Integrations\Checks\Testers;

use App\Integrations\Checks\CheckResult;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * OAuth apps (Google, Microsoft): asks the token endpoint to swap a made-up
 * code. "invalid_grant" means the vendor accepted our client ID and secret
 * and only refused the code; "invalid_client" means the keys are wrong.
 * Nothing is granted and no person's data is touched.
 */
final class OAuthClientTester extends HttpTester
{
    public function __construct(Factory $http, private readonly string $tokenUrl)
    {
        parent::__construct($http);
    }

    public function test(IntegrationDefinition $definition, CredentialEnvironment $environment, array $credentials): CheckResult
    {
        return $this->attempt(
            fn (PendingRequest $request) => $request->asForm()->post($this->tokenUrl, [
                'grant_type' => 'authorization_code',
                'code' => 'reos-connection-check',
                'redirect_uri' => url("integrations/{$definition->id}/callback"),
                'client_id' => $credentials['client_id'] ?? '',
                'client_secret' => $credentials['client_secret'] ?? '',
            ]),
            static function (Response $response): ?CheckResult {
                $error = $response->json('error');

                return match ($error) {
                    'invalid_grant' => CheckResult::passed(),
                    'invalid_client', 'unauthorized_client' => CheckResult::failed('rejected'),
                    default => null,
                };
            },
        );
    }
}
