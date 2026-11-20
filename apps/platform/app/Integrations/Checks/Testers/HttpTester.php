<?php

declare(strict_types=1);

namespace App\Integrations\Checks\Testers;

use App\Integrations\Checks\CheckResult;
use App\Integrations\Checks\ConnectionTester;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Shared plumbing for testers that call the vendor over HTTPS: short
 * timeouts, no retries, and a plain-words result for every failure.
 */
abstract class HttpTester implements ConnectionTester
{
    public function __construct(protected readonly Factory $http) {}

    public function contactsVendor(): bool
    {
        return true;
    }

    protected function request(): PendingRequest
    {
        return $this->http->connectTimeout(5)->timeout(10)->acceptJson()
            ->withUserAgent('REOS-connection-check');
    }

    /**
     * Runs the request and maps the common answers: 2xx passes, 401/403 means
     * the keys were rejected, anything else is a vendor error.
     *
     * @param  Closure(PendingRequest): Response  $send
     * @param  (Closure(Response): ?CheckResult)|null  $interpret  vendor-specific reading, null to fall through
     */
    protected function attempt(Closure $send, ?Closure $interpret = null): CheckResult
    {
        try {
            $response = $send($this->request());
        } catch (ConnectionException) {
            return CheckResult::failed('unreachable');
        }

        if ($interpret !== null && ($result = $interpret($response)) !== null) {
            return $result;
        }

        return match (true) {
            $response->successful() => CheckResult::passed(),
            in_array($response->status(), [401, 403], true) => CheckResult::failed('rejected'),
            default => CheckResult::failed('vendorError', ['status' => $response->status()]),
        };
    }
}
