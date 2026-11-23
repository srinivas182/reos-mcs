<?php

declare(strict_types=1);

namespace App\Integrations\Checks\Testers;

use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorUnavailable;
use App\Integrations\Checks\CheckResult;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use App\Mls\MlsVendor;
use App\Mls\Reso\ResoWebApi;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

/**
 * MLS feeds (B10.1): signs in the way the vendor expects (Trestle swaps the
 * client credentials for a token) and reads one office record. Nothing at the
 * MLS changes.
 */
final class MlsFeedTester extends HttpTester
{
    public function __construct(Factory $http, private readonly Cache $cache)
    {
        parent::__construct($http);
    }

    public function test(IntegrationDefinition $definition, CredentialEnvironment $environment, array $credentials): CheckResult
    {
        $mls = $definition->mls;

        if ($mls === null || $mls->vendor === MlsVendor::ToConfirm) {
            return CheckResult::unverified('mlsVendor');
        }

        if (($credentials['server_url'] ?? '') === '') {
            $credentials['server_url'] = $definition->field('server_url')->default ?? '';
        }

        $api = new ResoWebApi($this->http, $this->cache, $definition->id, $mls, $credentials);

        try {
            $token = $api->token();
        } catch (AccessRevoked) {
            return CheckResult::failed('rejected');
        } catch (VendorUnavailable) {
            return CheckResult::failed('unreachable');
        }

        $filter = $mls->vendor === MlsVendor::MlsGrid ? ['$filter' => "OriginatingSystemName eq '{$mls->dataset}'"] : [];

        return $this->attempt(static fn (PendingRequest $request) => $request->withToken($token)->get($api->base().'/Office', ['$top' => '1', ...$filter]));
    }
}
