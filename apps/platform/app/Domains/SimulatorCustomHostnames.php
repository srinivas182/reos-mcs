<?php

declare(strict_types=1);

namespace App\Domains;

use Illuminate\Support\Str;

/**
 * Demo custom domains (A4.2b): the same records Cloudflare would ask for,
 * and the domain goes live with SSL on the first check, as if DNS had
 * already spread.
 */
final readonly class SimulatorCustomHostnames implements CustomHostnames
{
    public function __construct(private string $cnameTarget) {}

    public function create(string $hostname): HostnameState
    {
        $id = 'sim-'.Str::lower(Str::random(16));

        return new HostnameState($id, HostnameState::PENDING, HostnameState::PENDING, $this->records($hostname, $id));
    }

    public function refresh(string $providerId, string $hostname): HostnameState
    {
        return new HostnameState($providerId, HostnameState::ACTIVE, HostnameState::ACTIVE, $this->records($hostname, $providerId));
    }

    public function delete(string $providerId): void {}

    /** @return list<array{type: string, name: string, value: string}> */
    private function records(string $hostname, string $id): array
    {
        return [
            ['type' => 'CNAME', 'name' => $hostname, 'value' => $this->cnameTarget],
            ['type' => 'TXT', 'name' => '_reos-verify.'.$hostname, 'value' => 'reos-'.substr(hash('sha256', $id), 0, 32)],
        ];
    }
}
