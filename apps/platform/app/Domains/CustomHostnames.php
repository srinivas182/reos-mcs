<?php

declare(strict_types=1);

namespace App\Domains;

/**
 * Tenants' own domains with automatic SSL (A4.2b, ADR 0016): Cloudflare
 * for SaaS live, the Simulator in development and tests.
 */
interface CustomHostnames
{
    public function create(string $hostname): HostnameState;

    public function refresh(string $providerId, string $hostname): HostnameState;

    public function delete(string $providerId): void;
}
