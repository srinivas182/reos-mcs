<?php

declare(strict_types=1);

namespace App\Connections;

use App\Connections\Drivers\GoogleCalendar\GoogleCalendarDriver;
use App\Connections\Drivers\GoogleCalendar\GoogleCalendarSimulator;
use App\Integrations\Catalogue;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationDefinition;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use LogicException;

/**
 * Which driver serves a tool right now. The super admin's run mode decides
 * (ADR 0009): demo data uses the Simulator; test and live keys use the real
 * driver with those keys. A tool that is Off, or paused by health checks,
 * has no driver, so nothing pretends to work.
 *
 * A tool joins the engine by adding its real driver and Simulator here.
 */
final readonly class ConnectorRegistry
{
    /** @var array<string, class-string<ConnectorDriver>> */
    private const SIMULATORS = [
        GoogleCalendarDriver::INTEGRATION => GoogleCalendarSimulator::class,
    ];

    public function __construct(
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    /** @return list<string> Every tool the engine knows, connectable or not. */
    public static function ids(): array
    {
        return array_keys(self::SIMULATORS);
    }

    /** Tools people can connect: in the engine, and owned by a person or organisation. */
    public function supports(string $integrationId): bool
    {
        return isset(self::SIMULATORS[$integrationId]) && $this->definition($integrationId) !== null;
    }

    public function definition(string $integrationId): ?IntegrationDefinition
    {
        $definition = $this->catalogue->find($integrationId);

        return $definition !== null && in_array($definition->accounts->value, ['user', 'tenant'], true) ? $definition : null;
    }

    /** The mode connections run in now; Off when switched off or paused. */
    public function mode(string $integrationId): IntegrationMode
    {
        $definition = $this->definition($integrationId);

        return $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();
    }

    public function driver(string $integrationId, IntegrationMode $mode): ConnectorDriver
    {
        $simulator = self::SIMULATORS[$integrationId] ?? throw new LogicException("No connector for {$integrationId}.");

        return match ($mode) {
            IntegrationMode::Simulator => new $simulator,
            IntegrationMode::Sandbox => $this->real($integrationId, CredentialEnvironment::Sandbox),
            IntegrationMode::Live => $this->real($integrationId, CredentialEnvironment::Live),
            IntegrationMode::Off => throw new LogicException("{$integrationId} is off."),
        };
    }

    /** The tool's per-minute allowance in a mode (Off: none). */
    public function requestsPerMinute(string $integrationId, IntegrationMode $mode): int
    {
        return $mode === IntegrationMode::Off || ! isset(self::SIMULATORS[$integrationId]) ? 60 : $this->driver($integrationId, $mode)->requestsPerMinute();
    }

    private function real(string $integrationId, CredentialEnvironment $environment): ConnectorDriver
    {
        $keys = $this->vault->get($integrationId, $environment);
        $value = static fn (string $key): string => is_string($keys[$key] ?? null) ? $keys[$key] : '';

        return match ($integrationId) {
            GoogleCalendarDriver::INTEGRATION => new GoogleCalendarDriver($value('client_id'), $value('client_secret')),
            default => throw new LogicException("No real driver for {$integrationId}."),
        };
    }
}
