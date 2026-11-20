<?php

declare(strict_types=1);

namespace App\Monitoring;

use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * Builds the OpenTelemetry tracer that sends request traces to Grafana
 * Cloud over OTLP/HTTP (JSON). Tests swap the exporter for an in-memory one.
 */
class TracerFactory
{
    public function make(MonitoringSettings $settings): TracerProviderInterface
    {
        return TracerProvider::builder()
            ->setResource(ResourceInfo::create(Attributes::create([
                'service.name' => 'reos-platform',
                'service.version' => config()->string('reos.version'),
                'deployment.environment.name' => config()->string('app.env'),
            ])))
            ->addSpanProcessor(new BatchSpanProcessor($this->exporter($settings), ClockFactory::getDefault()))
            ->build();
    }

    protected function exporter(MonitoringSettings $settings): SpanExporterInterface
    {
        $endpoint = rtrim((string) $settings->otlpEndpoint, '/').'/v1/traces';
        $auth = base64_encode("{$settings->otlpInstanceId}:{$settings->otlpToken}");

        return new SpanExporter((new OtlpHttpTransportFactory)->create(
            $endpoint,
            ContentTypes::JSON,
            ['Authorization' => "Basic {$auth}"],
            timeout: 5.0,
            maxRetries: 1,
        ));
    }
}
