<?php

declare(strict_types=1);

namespace App\Sso;

/**
 * Addresses a tenant admin types in (metadata URL, OIDC issuer) are fetched
 * by REOS's servers. In production they must be HTTPS on the public
 * internet, so nobody can point REOS at its own internal network.
 */
final class ProviderAddress
{
    public static function allowed(string $url, ?bool $strict = null): bool
    {
        $strict ??= app()->isProduction();
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! $strict) {
            return in_array($parts['scheme'], ['http', 'https'], true);
        }

        if ($parts['scheme'] !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = trim($parts['host'], '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
