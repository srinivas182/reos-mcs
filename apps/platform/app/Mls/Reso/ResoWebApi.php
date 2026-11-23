<?php

declare(strict_types=1);

namespace App\Mls\Reso;

use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Mls\MlsDefinition;
use App\Mls\MlsVendor;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use LogicException;

/**
 * The one RESO Web API (OData) driver (ADR 0042), for every data vendor:
 *
 * - **Bridge:** a server token; photos come inline with each listing.
 * - **Trestle:** client credentials swapped for a token; photos by `$expand=Media`.
 * - **MLS Grid:** an access token; every query names the MLS
 *   (`OriginatingSystemName`), photos by `$expand=Media`, and a record the
 *   MLS withdraws arrives with `MlgCanView` false.
 *
 * Replication follows `ModificationTimestamp`, oldest first, and the
 * vendor's `@odata.nextLink`. A next link must stay on the vendor's host.
 */
final readonly class ResoWebApi implements ResoSource
{
    public const PAGE_SIZE = 200;

    /**
     * @param  array<string, string>  $credentials  saved keys: server_url, then access_token or client_id and client_secret
     */
    public function __construct(
        private Factory $http,
        private Cache $cache,
        private string $integration,
        private MlsDefinition $mls,
        private array $credentials,
    ) {
        if ($mls->vendor === MlsVendor::ToConfirm) {
            throw new LogicException("{$integration} has no delivery vendor yet.");
        }
    }

    public function id(): string
    {
        return 'reso';
    }

    public function changes(string $resource, ?string $since, ?string $next): ResoPage
    {
        if ($next !== null) {
            return $this->get($next, []);
        }

        $filters = $this->scope();

        if ($since !== null) {
            $filters[] = "ModificationTimestamp ge {$since}";
        }

        $query = ['$orderby' => 'ModificationTimestamp asc', '$top' => (string) self::PAGE_SIZE];

        if ($filters !== []) {
            $query['$filter'] = implode(' and ', $filters);
        }

        if ($resource === 'Property' && $this->mls->vendor->expandsMedia()) {
            $query['$expand'] = 'Media';
        }

        return $this->get($this->base()."/{$resource}", $query);
    }

    public function listingKeys(?string $next): ResoPage
    {
        if ($next !== null) {
            return $this->get($next, []);
        }

        $query = ['$select' => $this->mls->vendor === MlsVendor::MlsGrid ? 'ListingKey,MlgCanView' : 'ListingKey', '$top' => '1000'];
        $filters = $this->scope();

        if ($filters !== []) {
            $query['$filter'] = implode(' and ', $filters);
        }

        return $this->get($this->base().'/Property', $query);
    }

    /**
     * A token for the vendor, kept until shortly before it expires.
     *
     * @throws AccessRevoked|VendorUnavailable
     */
    public function token(): string
    {
        if ($this->mls->vendor !== MlsVendor::Trestle) {
            return $this->credentials['access_token'] ?? '';
        }

        $key = $this->tokenKey();
        $cached = $this->cache->get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->request()->asForm()->post($this->tokenUrl(), [
                'grant_type' => 'client_credentials',
                'scope' => 'api',
                'client_id' => $this->credentials['client_id'] ?? '',
                'client_secret' => $this->credentials['client_secret'] ?? '',
            ]);
        } catch (ConnectionException) {
            throw new VendorUnavailable("{$this->integration} token endpoint unreachable.");
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw $response->status() >= 500 ? new VendorUnavailable("{$this->integration} token endpoint failed.") : new AccessRevoked("{$this->integration} refused REOS's client credentials.");
        }

        $expires = $response->json('expires_in');
        $this->cache->put($key, $token, max(60, (is_int($expires) ? $expires : 3600) - 120));

        return $token;
    }

    /** The OData service root (`…/OData/har`, `…/trestle/odata`, `…/v2`). */
    public function base(): string
    {
        return rtrim($this->credentials['server_url'] ?? '', '/');
    }

    private function tokenKey(): string
    {
        return 'mls-token:'.$this->integration.':'.hash('sha256', ($this->credentials['client_id'] ?? '').'|'.($this->credentials['client_secret'] ?? ''));
    }

    /** Trestle's token endpoint sits beside its OData root. */
    private function tokenUrl(): string
    {
        return (string) preg_replace('#/odata$#i', '', $this->base()).'/oidc/connect/token';
    }

    /** @return list<string> filters every query carries */
    private function scope(): array
    {
        return $this->mls->vendor === MlsVendor::MlsGrid ? ["OriginatingSystemName eq '{$this->mls->dataset}'"] : [];
    }

    /**
     * @param  array<string, string>  $query
     *
     * @throws AccessRevoked|VendorRateLimited|VendorUnavailable
     */
    private function get(string $url, array $query): ResoPage
    {
        if (! $this->sameHost($url)) {
            throw new VendorUnavailable("{$this->integration} sent a next link to another host.");
        }

        try {
            // A next link carries its own query: passing even an empty one would replace it.
            $request = $this->request()->withToken($this->token());
            $response = $query === [] ? $request->get($url) : $request->get($url, $query);
        } catch (ConnectionException) {
            throw new VendorUnavailable("{$this->integration} unreachable.");
        }

        $this->guard($response);
        $body = $response->json();
        $values = is_array($body) ? ($body['value'] ?? null) : null;
        // Read by key: the name has a dot, which `json('…')` would treat as a path.
        $next = is_array($body) ? ($body['@odata.nextLink'] ?? null) : null;
        $records = [];

        foreach (is_array($values) ? $values : [] as $record) {
            if (is_array($record)) {
                $records[] = ResoRecords::fields($record);
            }
        }

        return new ResoPage($records, is_string($next) && $next !== '' ? $next : null);
    }

    /** @throws AccessRevoked|VendorRateLimited|VendorUnavailable */
    private function guard(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 429) {
            $after = $response->header('Retry-After');

            throw new VendorRateLimited(is_numeric($after) ? max(1, (int) $after) : 60);
        }

        if (in_array($response->status(), [401, 403], true)) {
            $this->cache->forget($this->tokenKey());

            throw new AccessRevoked("{$this->integration} refused REOS's keys ({$response->status()}).");
        }

        throw new VendorUnavailable("{$this->integration} answered {$response->status()}.");
    }

    private function sameHost(string $url): bool
    {
        $host = parse_url($this->base(), PHP_URL_HOST);

        return is_string($host) && $host !== '' && parse_url($url, PHP_URL_HOST) === $host && parse_url($url, PHP_URL_SCHEME) === 'https';
    }

    private function request(): PendingRequest
    {
        return $this->http->connectTimeout(10)->timeout(60)->acceptJson()->withUserAgent('REOS-MLS/1.0');
    }
}
