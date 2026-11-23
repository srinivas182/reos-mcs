<?php

declare(strict_types=1);

namespace App\Connections;

use App\Connections\Drivers\BoldTrail\BoldTrailDriver;
use App\Connections\Drivers\BoldTrail\BoldTrailSimulator;
use App\Connections\Drivers\FollowUpBoss\FollowUpBossDriver;
use App\Connections\Drivers\FollowUpBoss\FollowUpBossSimulator;
use App\Connections\Drivers\GoogleCalendar\GoogleCalendarDriver;
use App\Connections\Drivers\GoogleCalendar\GoogleCalendarSimulator;
use App\Connections\Drivers\Lofty\LoftyDriver;
use App\Connections\Drivers\Lofty\LoftySimulator;
use App\Connections\Drivers\OutlookCalendar\OutlookCalendarDriver;
use App\Connections\Drivers\OutlookCalendar\OutlookCalendarSimulator;
use App\Integrations\Catalogue;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationDefinition;
use App\Integrations\IntegrationMode;
use App\Services\Catalogue\IntegrationCatalogue;
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
        OutlookCalendarDriver::INTEGRATION => OutlookCalendarSimulator::class,
        FollowUpBossDriver::INTEGRATION => FollowUpBossSimulator::class,
        BoldTrailDriver::INTEGRATION => BoldTrailSimulator::class,
        LoftyDriver::INTEGRATION => LoftySimulator::class,
    ];

    /** Tools people connect with their own API key (B7.2) rather than by signing in. */
    private const API_KEYS = [FollowUpBossDriver::INTEGRATION, BoldTrailDriver::INTEGRATION, LoftyDriver::INTEGRATION];

    /** Tools whose drivers are also a `CalendarDriver`. */
    private const CALENDARS = [GoogleCalendarDriver::INTEGRATION, OutlookCalendarDriver::INTEGRATION];

    public function __construct(
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
        private IntegrationCatalogue $offer,
    ) {}

    /** @var array<string, array{can: list<string>, never: list<string>}> */
    private const CONSENT = [
        GoogleCalendarDriver::INTEGRATION => GoogleCalendarDriver::CONSENT,
        OutlookCalendarDriver::INTEGRATION => OutlookCalendarDriver::CONSENT,
        FollowUpBossDriver::INTEGRATION => FollowUpBossDriver::CONSENT,
        BoldTrailDriver::INTEGRATION => BoldTrailDriver::CONSENT,
        LoftyDriver::INTEGRATION => LoftyDriver::CONSENT,
    ];

    /** @var array<string, array<string, string>> */
    private const STREAMS = [
        GoogleCalendarDriver::INTEGRATION => GoogleCalendarDriver::STREAMS,
        OutlookCalendarDriver::INTEGRATION => OutlookCalendarDriver::STREAMS,
        FollowUpBossDriver::INTEGRATION => FollowUpBossDriver::STREAMS,
        BoldTrailDriver::INTEGRATION => BoldTrailDriver::STREAMS,
        LoftyDriver::INTEGRATION => LoftyDriver::STREAMS,
    ];

    /**
     * What a tool lets REOS do, and what REOS never does there.
     *
     * @return array{can: list<string>, never: list<string>}|null
     */
    public static function consent(string $integrationId): ?array
    {
        return self::CONSENT[$integrationId] ?? null;
    }

    /**
     * Each stream and its direction (`read`, `write` or `both`).
     *
     * @return array<string, string>
     */
    public static function streamDirections(string $integrationId): array
    {
        return self::STREAMS[$integrationId] ?? [];
    }

    /** Connected with the person's own API key (B7.2). */
    public static function usesApiKey(string $integrationId): bool
    {
        return in_array($integrationId, self::API_KEYS, true);
    }

    /** A calendar tool: REOS writes events to it (B4.1). */
    public static function isCalendar(string $integrationId): bool
    {
        return in_array($integrationId, self::CALENDARS, true);
    }

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

    /**
     * The mode connections run in now: Off when switched off, paused by health
     * checks, paused in the catalogue (kill switch) or past retirement.
     */
    public function mode(string $integrationId): IntegrationMode
    {
        $definition = $this->definition($integrationId);

        if ($definition === null || ! $this->offer->syncAllowed($integrationId)) {
            return IntegrationMode::Off;
        }

        return $this->hub->status($definition)->effectiveMode();
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
            OutlookCalendarDriver::INTEGRATION => new OutlookCalendarDriver($value('client_id'), $value('client_secret')),
            FollowUpBossDriver::INTEGRATION => new FollowUpBossDriver($value('system_name'), $value('system_key')),
            BoldTrailDriver::INTEGRATION => new BoldTrailDriver($value('server_url')),
            LoftyDriver::INTEGRATION => new LoftyDriver,
            default => throw new LogicException("No real driver for {$integrationId}."),
        };
    }
}
