<?php

declare(strict_types=1);

use App\Integrations\Catalogue;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\IntegrationCheck;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Monitoring\MonitoringRuntime;
use App\Monitoring\MonitoringSettings;
use App\Monitoring\TracerFactory;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Sentry\SentrySdk;

uses(RefreshDatabase::class);

const SENTRY_KEYS = [
    'dsn' => 'https://aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa@o1.ingest.sentry.io/1',
    'browser_dsn' => 'https://bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb@o1.ingest.sentry.io/2',
    'mobile_dsn' => 'https://cccccccccccccccccccccccccccccccc@o1.ingest.sentry.io/3',
];

const GRAFANA_KEYS = [
    'otlp_endpoint' => 'https://otlp.example.grafana.net/otlp',
    'instance_id' => '12345',
    'access_token' => 'glc_fake-secret-token-0001',
];

/** Saves keys and switches the service to run on them (as the super admin would). */
function runOn(string $id, IntegrationMode $mode, array $keys): void
{
    $environment = $mode->environment() ?? CredentialEnvironment::Live;
    app(CredentialVault::class)->put($id, $environment, $keys, null);
    IntegrationCheck::query()->create([
        'integration_id' => $id, 'environment' => $environment, 'outcome' => 'passed',
        'message' => 'integrations.checks.passed', 'parameters' => [], 'trigger' => 'manual', 'created_at' => now()->addMinute(),
    ]);
    app(IntegrationHub::class)->setMode(app(Catalogue::class)->find($id), $mode, null);
}

function sentryDsn(): ?string
{
    return SentrySdk::getCurrentHub()->getClient()?->getOptions()->getDsn()?->__toString();
}

it('sends nothing until a service runs on real keys', function () {
    app(CredentialVault::class)->put('sentry', CredentialEnvironment::Live, SENTRY_KEYS, null);

    $runtime = app(MonitoringRuntime::class);
    $runtime->apply();

    expect($runtime->settings())->toEqual(new MonitoringSettings)
        ->and($runtime->clientConfig('web')['sentry'])->toBeNull()
        ->and(sentryDsn())->toBeNull()
        ->and($runtime->tracer())->toBeNull();
});

it('switches Sentry on and off from settings, without a deploy', function () {
    $runtime = app(MonitoringRuntime::class);

    runOn('sentry', IntegrationMode::Live, SENTRY_KEYS);
    $runtime->apply();
    expect(sentryDsn())->toBe(SENTRY_KEYS['dsn']);

    app(IntegrationHub::class)->setMode(app(Catalogue::class)->find('sentry'), IntegrationMode::Off, null);
    $runtime->apply();
    expect(sentryDsn())->toBeNull();
});

it('treats a paused service as off', function () {
    runOn('sentry', IntegrationMode::Live, SENTRY_KEYS);
    IntegrationSetting::query()->whereKey('sentry')->update(['paused_at' => now()]);
    app(MonitoringRuntime::class)->forget();

    expect(app(MonitoringRuntime::class)->clientConfig('web')['sentry'])->toBeNull();
});

it('gives the web and phone apps their own public settings, never secrets', function () {
    runOn('sentry', IntegrationMode::Sandbox, SENTRY_KEYS);
    runOn('posthog', IntegrationMode::Live, ['project_key' => 'phc_public_key', 'host' => 'https://us.i.posthog.com']);
    runOn('grafana-otel', IntegrationMode::Live, GRAFANA_KEYS);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monitoring.sentry.dsn', SENTRY_KEYS['browser_dsn'])
            ->where('monitoring.posthog', ['key' => 'phc_public_key', 'host' => 'https://us.i.posthog.com'])
            ->where('monitoring.environment', 'testing'))
        ->assertDontSee(GRAFANA_KEYS['access_token'], false);

    $this->getJson('/api/v1/app-config')
        ->assertOk()
        ->assertExactJson([
            'environment' => 'testing',
            'release' => config('reos.version'),
            'sentry' => ['dsn' => SENTRY_KEYS['mobile_dsn']],
            'posthog' => ['key' => 'phc_public_key', 'host' => 'https://us.i.posthog.com'],
            'sign_in' => ['google' => true, 'apple' => true, 'phone' => true, 'demo' => true],
            // Live messages are off without Reverb (B6.1).
            'realtime' => null,
        ]);

    // With Reverb: the public key and address only, never the secret.
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'reos-key', 'broadcasting.connections.reverb.secret' => 'reos-secret',
        'broadcasting.connections.reverb.options' => ['host' => 'ws.reos.com', 'port' => 443, 'scheme' => 'https']]);
    $this->getJson('/api/v1/app-config')
        ->assertJsonPath('realtime', ['key' => 'reos-key', 'host' => 'ws.reos.com', 'port' => 443, 'scheme' => 'https'])
        ->assertDontSee('reos-secret');
});

it('tells the phone app when everything is off', function () {
    $this->getJson('/api/v1/app-config')
        ->assertOk()
        ->assertJson(['sentry' => null, 'posthog' => null]);
});

it('limits app-config to 60 requests a minute', function () {
    foreach (range(1, 60) as $_) {
        $this->getJson('/api/v1/app-config')->assertOk();
    }

    $this->getJson('/api/v1/app-config')->assertTooManyRequests();
});

it('records one trace per request when Grafana is on, without personal data', function () {
    $exporter = new InMemoryExporter;
    app()->instance(TracerFactory::class, new class($exporter) extends TracerFactory
    {
        public function __construct(private readonly InMemoryExporter $memory) {}

        public function make(MonitoringSettings $settings): TracerProviderInterface
        {
            return TracerProvider::builder()->addSpanProcessor(new SimpleSpanProcessor($this->memory))->build();
        }
    });
    app()->forgetInstance(MonitoringRuntime::class);
    runOn('grafana-otel', IntegrationMode::Live, GRAFANA_KEYS);

    $this->getJson('/api/v1/health?email=someone@example.com')->assertOk();

    $spans = $exporter->getSpans();
    expect($spans)->toHaveCount(1)
        ->and($spans[0]->getName())->toBe('GET /api/v1/health')
        ->and($spans[0]->getAttributes()->toArray())->toBe([
            'http.request.method' => 'GET',
            'url.scheme' => 'http',
            'http.route' => '/api/v1/health',
            'http.response.status_code' => 200,
        ]);
});

it('keeps the health check working when the database is down', function () {
    // Rolled back with the test's transaction.
    Schema::drop('integration_settings');
    app(MonitoringRuntime::class)->forget();

    $this->getJson('/api/v1/health')->assertOk();
});
