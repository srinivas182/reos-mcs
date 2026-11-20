<?php

declare(strict_types=1);

namespace App\Monitoring;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Contracts\Cache\Repository as Cache;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface as SdkTracerProvider;
use Sentry\ClientBuilder;
use Sentry\SentrySdk;
use Throwable;

/**
 * Switches Sentry and Grafana tracing on and off from Settings ›
 * Integrations without a deploy (Sprint 2.4c, ADR 0009).
 *
 * The settings are cached until an integration changes. Each request (and
 * each queued job) calls {@see apply()}, which rebuilds the Sentry client and
 * the tracer only when the settings changed, so long-running Octane and
 * queue workers pick up a change on their next request.
 */
final class MonitoringRuntime
{
    public const CACHE_KEY = 'reos:monitoring:settings';

    private ?string $applied = null;

    private ?TracerProviderInterface $tracerProvider = null;

    public function __construct(
        private readonly Cache $cache,
        private readonly Catalogue $catalogue,
        private readonly IntegrationHub $hub,
        private readonly CredentialVault $vault,
        private readonly TracerFactory $tracers,
    ) {}

    public function settings(): MonitoringSettings
    {
        $cached = $this->cache->get(self::CACHE_KEY);

        if (is_array($cached)) {
            return MonitoringSettings::fromArray($cached);
        }

        try {
            $settings = $this->resolve();
        } catch (Throwable $exception) {
            // Database not reachable (or not migrated yet): monitoring stays
            // off, nothing is cached, and the next request tries again.
            // Monitoring must never be the reason a request fails, which also
            // keeps /api/v1/health and /up independent of the database.
            report($exception);

            return new MonitoringSettings;
        }

        $this->cache->forever(self::CACHE_KEY, $settings->toArray());

        return $settings;
    }

    /** Called when any integration changes. */
    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /** Brings Sentry and tracing in line with the current settings. */
    public function apply(): MonitoringSettings
    {
        $settings = $this->settings();
        $fingerprint = $settings->fingerprint();

        if ($fingerprint === $this->applied) {
            return $settings;
        }

        $this->applied = $fingerprint;

        // A client without a DSN sends nothing, so this also switches Sentry off.
        SentrySdk::getCurrentHub()->bindClient(ClientBuilder::create([
            'dsn' => $settings->sentryDsn,
            'environment' => config()->string('app.env'),
            'release' => config()->string('reos.version'),
            'send_default_pii' => false,
            'traces_sample_rate' => null,
        ])->getClient());

        $this->flush();
        $this->tracerProvider = $settings->tracingEnabled() ? $this->tracers->make($settings) : null;

        return $settings;
    }

    public function tracer(): ?TracerInterface
    {
        return $this->tracerProvider?->getTracer('reos-platform');
    }

    /** Sends any buffered spans (end of a request on PHP-FPM, or before a rebuild). */
    public function flush(): void
    {
        if ($this->tracerProvider instanceof SdkTracerProvider) {
            $this->tracerProvider->forceFlush();
        }
    }

    /**
     * What the web or phone app needs to start error tracking and analytics.
     * Only values vendors publish for browsers and apps (DSNs, the PostHog
     * project key); never secrets.
     *
     * @return array{environment: string, release: string, sentry: array{dsn: string}|null, posthog: array{key: string, host: string}|null}
     */
    public function clientConfig(string $surface): array
    {
        $settings = $this->settings();
        $dsn = $surface === 'mobile' ? $settings->sentryMobileDsn : $settings->sentryBrowserDsn;
        $dsn ??= $settings->sentryDsn;

        return [
            'environment' => config()->string('app.env'),
            'release' => config()->string('reos.version'),
            'sentry' => $dsn !== null ? ['dsn' => $dsn] : null,
            'posthog' => $settings->posthogKey !== null && $settings->posthogHost !== null
                ? ['key' => $settings->posthogKey, 'host' => $settings->posthogHost]
                : null,
        ];
    }

    private function resolve(): MonitoringSettings
    {
        $values = [];

        foreach (['sentry', 'posthog', 'grafana-otel'] as $id) {
            $definition = $this->catalogue->find($id);
            $environment = $definition === null ? null : $this->hub->status($definition)->effectiveMode()->environment();
            $values[$id] = $environment === null ? [] : $this->vault->get($id, $environment);
        }

        return new MonitoringSettings(
            sentryDsn: $values['sentry']['dsn'] ?? null,
            sentryBrowserDsn: $values['sentry']['browser_dsn'] ?? null,
            sentryMobileDsn: $values['sentry']['mobile_dsn'] ?? null,
            posthogKey: $values['posthog']['project_key'] ?? null,
            posthogHost: $values['posthog']['host'] ?? null,
            otlpEndpoint: $values['grafana-otel']['otlp_endpoint'] ?? null,
            otlpInstanceId: $values['grafana-otel']['instance_id'] ?? null,
            otlpToken: $values['grafana-otel']['access_token'] ?? null,
        );
    }
}
