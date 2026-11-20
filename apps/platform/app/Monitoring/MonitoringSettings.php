<?php

declare(strict_types=1);

namespace App\Monitoring;

/**
 * What error tracking, analytics and tracing use right now, worked out from
 * Settings › Integrations (Sentry, PostHog, Grafana). A service sends data
 * only when it runs on the vendor's test system or live and isn't paused;
 * on demo data or Off it sends nothing.
 */
final readonly class MonitoringSettings
{
    public function __construct(
        public ?string $sentryDsn = null,
        public ?string $sentryBrowserDsn = null,
        public ?string $sentryMobileDsn = null,
        public ?string $posthogKey = null,
        public ?string $posthogHost = null,
        public ?string $otlpEndpoint = null,
        public ?string $otlpInstanceId = null,
        public ?string $otlpToken = null,
    ) {}

    public function tracingEnabled(): bool
    {
        return $this->otlpEndpoint !== null && $this->otlpInstanceId !== null && $this->otlpToken !== null;
    }

    /** Changes whenever anything above changes; used to rebuild clients only when needed. */
    public function fingerprint(): string
    {
        return hash('xxh128', serialize($this));
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'sentryDsn' => $this->sentryDsn,
            'sentryBrowserDsn' => $this->sentryBrowserDsn,
            'sentryMobileDsn' => $this->sentryMobileDsn,
            'posthogKey' => $this->posthogKey,
            'posthogHost' => $this->posthogHost,
            'otlpEndpoint' => $this->otlpEndpoint,
            'otlpInstanceId' => $this->otlpInstanceId,
            'otlpToken' => $this->otlpToken,
        ];
    }

    /** @param  array<mixed>  $data */
    public static function fromArray(array $data): self
    {
        $string = static fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;

        return new self(
            sentryDsn: $string('sentryDsn'),
            sentryBrowserDsn: $string('sentryBrowserDsn'),
            sentryMobileDsn: $string('sentryMobileDsn'),
            posthogKey: $string('posthogKey'),
            posthogHost: $string('posthogHost'),
            otlpEndpoint: $string('otlpEndpoint'),
            otlpInstanceId: $string('otlpInstanceId'),
            otlpToken: $string('otlpToken'),
        );
    }
}
