<?php

declare(strict_types=1);

namespace App\Sso;

/**
 * REOS's side of a tenant's single sign-on: the addresses its identity
 * provider admin registers. They live on the tenant's own address (live
 * custom domain, else `<slug>.reos.com`), so they never change with the
 * page a member started on.
 */
final readonly class ServiceProvider
{
    public function __construct(
        /** Scheme, host and port of the tenant's address, no trailing slash. */
        public string $origin,
        public string $entityId,
        public string $acsUrl,
        public string $metadataUrl,
        public string $startUrl,
    ) {}

    public static function at(string $origin, string $slug): self
    {
        $base = "{$origin}/sso/{$slug}";

        return new self($origin, "{$base}/metadata", "{$base}/callback", "{$base}/metadata", "{$base}/start");
    }

    /** OIDC calls the ACS address the redirect URI. */
    public function redirectUri(): string
    {
        return $this->acsUrl;
    }
}
