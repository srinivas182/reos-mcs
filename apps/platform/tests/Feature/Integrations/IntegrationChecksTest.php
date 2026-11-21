<?php

declare(strict_types=1);

use App\Integrations\Catalogue;
use App\Integrations\Checks\CheckOutcome;
use App\Integrations\Checks\TesterRegistry;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\IntegrationCheck;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Notifications\IntegrationPaused;
use App\Services\Integrations\HealthMonitor;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    $this->admin = User::factory()->superAdmin()->create(['name' => 'REOS Operations']);
    $this->confirmed = ['auth.password_confirmed_at' => time()];
});

/** Saves keys straight into the vault (the keys screen is covered elsewhere). */
function keys(string $id, CredentialEnvironment $environment, array $values): void
{
    app(CredentialVault::class)->put($id, $environment, $values, null);
}

function testKeys(object $test, string $id, string $environment = 'live')
{
    return $test->actingAs($test->admin)->withSession($test->confirmed)
        ->post(route('integrations.checks.store', [$id, $environment]));
}

const STRIPE = [
    'publishable_key' => 'pk_live_fake-0000-0001',
    'secret_key' => 'sk_live_fake-0000-0000-9876',
    'webhook_secret' => 'whsec_fake-0000-4321',
];

describe('Test connection', function () {
    it('passes when Stripe accepts the key, with a read-only request', function () {
        Http::fake(['api.stripe.com/v1/balance' => Http::response(['object' => 'balance'])]);
        keys('stripe', CredentialEnvironment::Live, STRIPE);

        testKeys($this, 'stripe')->assertRedirect();

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer '.STRIPE['secret_key']));

        $check = IntegrationCheck::query()->sole();
        expect($check->outcome)->toBe(CheckOutcome::Passed)
            ->and($check->message)->toBe('integrations.checks.passed')
            ->and($check->checked_by)->toBe($this->admin->id)
            ->and(json_encode($check->getAttributes()))->not->toContain('fake-0000-0000-9876')
            ->and(AuditEvent::query()->sole()->properties)->toBe(['environment' => 'live', 'outcome' => 'passed']);
    });

    it('says when the vendor rejects the keys', function () {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'Invalid API Key provided: sk_live_****9876']], 401)]);
        keys('stripe', CredentialEnvironment::Live, STRIPE);

        testKeys($this, 'stripe');

        $check = IntegrationCheck::query()->sole();
        expect($check->outcome)->toBe(CheckOutcome::Failed)
            ->and($check->message)->toBe('integrations.checks.rejected')
            ->and(json_encode($check->getAttributes()))->not->toContain('Invalid API Key');
    });

    it('catches test keys pasted into the live box without calling Stripe', function () {
        keys('stripe', CredentialEnvironment::Live, [...STRIPE, 'secret_key' => 'sk_test_fake-0000-0000-1234']);

        testKeys($this, 'stripe');

        expect(IntegrationCheck::query()->sole()->message)->toBe('integrations.checks.wrongEnvironment');
        Http::assertNothingSent();
    });

    it('says when the vendor cannot be reached', function () {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        keys('stripe', CredentialEnvironment::Live, STRIPE);

        testKeys($this, 'stripe');

        expect(IntegrationCheck::query()->sole()->message)->toBe('integrations.checks.unreachable');
    });

    it('checks Google keys by asking for a token with a made-up code', function (array $answer, int $status, string $message) {
        Http::fake(['oauth2.googleapis.com/token' => Http::response($answer, $status)]);
        keys('google-calendar', CredentialEnvironment::Sandbox, ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => 'fake-secret-123456']);

        testKeys($this, 'google-calendar', 'sandbox');

        expect(IntegrationCheck::query()->sole()->message)->toBe($message);
        Http::assertSent(fn (Request $request) => $request['code'] === 'reos-connection-check'
            && $request['redirect_uri'] === url('integrations/google-calendar/callback'));
    })->with([
        'keys accepted' => [['error' => 'invalid_grant'], 400, 'integrations.checks.passed'],
        'keys wrong' => [['error' => 'invalid_client'], 401, 'integrations.checks.rejected'],
        'vendor down' => [['error' => 'server_error'], 503, 'integrations.checks.vendorError'],
    ]);

    it('checks Twilio, PostHog and Grafana with read-only or empty requests', function () {
        Http::fake([
            'api.twilio.com/*' => Http::response(['status' => 'active']),
            'us.i.posthog.com/*' => Http::response(['featureFlags' => []]),
            'otlp.example.grafana.net/*' => Http::response([], 200),
        ]);
        keys('twilio-sms', CredentialEnvironment::Live, ['account_sid' => 'AC'.str_repeat('a', 32), 'auth_token' => 'fake-token-123456', 'from_number' => '+15125550100']);
        keys('posthog', CredentialEnvironment::Live, ['project_key' => 'phc_fake', 'host' => 'https://us.i.posthog.com']);
        keys('grafana-otel', CredentialEnvironment::Live, ['otlp_endpoint' => 'https://otlp.example.grafana.net/otlp', 'instance_id' => '12345', 'access_token' => 'glc_fake-123456']);

        testKeys($this, 'twilio-sms');
        testKeys($this, 'posthog');
        testKeys($this, 'grafana-otel');

        expect(IntegrationCheck::query()->pluck('outcome')->all())->toBe([CheckOutcome::Passed, CheckOutcome::Passed, CheckOutcome::Passed]);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/otlp/v1/traces') && $request['resourceSpans'] === []);
    });

    it('checks Sentry DSNs are well formed without sending an event', function () {
        keys('sentry', CredentialEnvironment::Live, ['dsn' => 'https://'.str_repeat('a', 32).'@o1.ingest.sentry.io/42', 'browser_dsn' => 'https://example.com/not-a-dsn']);

        testKeys($this, 'sentry');

        $check = IntegrationCheck::query()->sole();
        expect($check->message)->toBe('integrations.checks.invalidFormat')
            ->and($check->parameters)->toBe(['field' => 'browser_dsn']);
        Http::assertNothingSent();
    });

    it('marks keys as complete for vendors whose check arrives with their sprint', function () {
        keys('docusign', CredentialEnvironment::Live, ['integration_key' => 'fake-ik', 'secret_key' => 'fake-secret']);

        testKeys($this, 'docusign');

        $check = IntegrationCheck::query()->sole();
        expect($check->outcome)->toBe(CheckOutcome::Unverified)
            ->and($check->parameters)->toBe(['sprint' => 9]);
    });

    it('fails when required keys are missing', function () {
        testKeys($this, 'stripe');

        expect(IntegrationCheck::query()->sole()->message)->toBe('integrations.checks.missingKeys');
    });

    it('is limited to ten tests a minute', function () {
        foreach (range(1, 10) as $_) {
            testKeys($this, 'docusign');
        }

        testKeys($this, 'docusign')->assertTooManyRequests();
    });

    it('is closed to everyone except super admins', function () {
        $this->actingAs(User::factory()->create())->withSession($this->confirmed)
            ->post(route('integrations.checks.store', ['stripe', 'live']))
            ->assertForbidden();
    });

    it('shows the latest result on the page', function () {
        Http::fake(['api.stripe.com/*' => Http::response([], 401)]);
        keys('stripe', CredentialEnvironment::Live, STRIPE);
        testKeys($this, 'stripe');

        $this->actingAs($this->admin)->withSession($this->confirmed)
            ->get(route('integrations.show', 'stripe'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('integration.checks.live.outcome', 'failed')
                ->where('integration.checks.live.message', 'integrations.checks.rejected')
                ->where('integration.checks.live.checked_by', 'REOS Operations')
                ->where('integration.checks.sandbox', null)
                ->where('integration.modes.3.reason', 'integrations.mode.liveTestFailed'));
    });
});

describe('going live', function () {
    it('needs a passing test of the current live keys', function () {
        Http::fake(['api.stripe.com/*' => Http::response([])]);
        keys('stripe', CredentialEnvironment::Live, STRIPE);
        $go = fn () => $this->actingAs($this->admin)->withSession($this->confirmed)
            ->patch(route('integrations.mode.update', 'stripe'), ['mode' => 'live']);

        $go()->assertSessionHasErrors(['mode' => 'Test the live keys first.']);

        testKeys($this, 'stripe');
        $this->travel(1)->minutes();
        // Changing the keys after the test means testing again.
        keys('stripe', CredentialEnvironment::Live, [...STRIPE, 'webhook_secret' => 'whsec_fake-0000-9999']);
        $go()->assertSessionHasErrors(['mode' => 'Test the live keys first.']);

        $this->travel(1)->minutes();
        testKeys($this, 'stripe');
        $go()->assertSessionHasNoErrors();

        expect(IntegrationSetting::query()->find('stripe')?->mode)->toBe(IntegrationMode::Live);
    });

    it('accepts complete keys for vendors without an automatic check yet', function () {
        keys('docusign', CredentialEnvironment::Live, ['integration_key' => 'fake-ik', 'secret_key' => 'fake-secret']);
        testKeys($this, 'docusign');

        $this->actingAs($this->admin)->withSession($this->confirmed)
            ->patch(route('integrations.mode.update', 'docusign'), ['mode' => 'live'])
            ->assertSessionHasNoErrors();
    });
});

describe('health checks', function () {
    beforeEach(function () {
        Notification::fake();
        keys('stripe', CredentialEnvironment::Live, STRIPE);
        IntegrationSetting::query()->create(['integration_id' => 'stripe', 'mode' => IntegrationMode::Live]);
        // Running on demo data or with no automatic check: never checked on a schedule.
        IntegrationSetting::query()->create(['integration_id' => 'twilio-sms', 'mode' => IntegrationMode::Simulator]);
        keys('docusign', CredentialEnvironment::Live, ['integration_key' => 'fake-ik', 'secret_key' => 'fake-secret']);
        IntegrationSetting::query()->create(['integration_id' => 'docusign', 'mode' => IntegrationMode::Live]);
    });

    it('checks only services running on vendor systems, every 15 minutes', function () {
        Http::fake(['api.stripe.com/*' => Http::response([])]);

        $this->artisan('reos:integrations:health')->expectsOutputToContain('Checked 1, failed 0, paused 0')->assertSuccessful();

        expect(IntegrationCheck::query()->sole()->trigger->value)->toBe('scheduled')
            ->and(AuditEvent::query()->count())->toBe(0);

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'reos:integrations:health'));
        expect($event?->expression)->toBe('*/15 * * * *');
    });

    it('pauses after three failures in a row and emails the super admins', function () {
        Http::fake(['api.stripe.com/*' => Http::response([], 503)]);
        $other = User::factory()->create();

        app(HealthMonitor::class)->run();
        app(HealthMonitor::class)->run();
        expect(IntegrationSetting::query()->find('stripe')?->paused_at)->toBeNull();
        Notification::assertNothingSent();

        expect(app(HealthMonitor::class)->run())->toBe(['checked' => 1, 'failed' => 1, 'paused' => 1]);

        $setting = IntegrationSetting::query()->findOrFail('stripe');
        expect($setting->paused_at)->not->toBeNull()
            ->and($setting->consecutive_failures)->toBe(3)
            ->and(app(IntegrationHub::class)->status(app(Catalogue::class)->find('stripe'))->effectiveMode())->toBe(IntegrationMode::Off)
            ->and(AuditEvent::query()->sole()->action)->toBe('integration.paused');

        Notification::assertSentTo($this->admin, IntegrationPaused::class, function (IntegrationPaused $notification) {
            $mail = $notification->toMail($this->admin);

            return $mail->subject === 'Stripe (Checkout, Billing, Tax) was paused'
                && in_array('Last result: The vendor answered with an error (code 503). Try again later.', $mail->introLines, true);
        });
        Notification::assertNotSentTo($other, IntegrationPaused::class);

        // Paused services are not checked again until someone fixes them.
        expect(app(HealthMonitor::class)->run()['checked'])->toBe(0);
    });

    it('starts counting again after a passing check', function () {
        Http::fakeSequence('api.stripe.com/*')
            ->push([], 503)->push([], 503)->push([])->push([], 503);

        foreach (range(1, 4) as $_) {
            app(HealthMonitor::class)->run();
        }

        expect(IntegrationSetting::query()->findOrFail('stripe')->consecutive_failures)->toBe(1);
    });

    it('switches back on when the super admin tests the keys and they pass', function () {
        IntegrationSetting::query()->whereKey('stripe')->update(['paused_at' => now(), 'consecutive_failures' => 3]);
        Http::fake(['api.stripe.com/*' => Http::response([])]);

        testKeys($this, 'stripe');

        $setting = IntegrationSetting::query()->findOrFail('stripe');
        expect($setting->paused_at)->toBeNull()
            ->and($setting->consecutive_failures)->toBe(0)
            ->and($setting->mode)->toBe(IntegrationMode::Live)
            ->and(AuditEvent::query()->pluck('action')->all())->toBe(['integration.checked', 'integration.resumed']);
    });

    it('stays paused when the test still fails', function () {
        IntegrationSetting::query()->whereKey('stripe')->update(['paused_at' => now(), 'consecutive_failures' => 3]);
        Http::fake(['api.stripe.com/*' => Http::response([], 401)]);

        testKeys($this, 'stripe');

        expect(IntegrationSetting::query()->findOrFail('stripe')->paused_at)->not->toBeNull();
    });
});

it('only registers testers for services in the register', function () {
    $catalogue = app(Catalogue::class);

    foreach (array_keys(TesterRegistry::testers()) as $id) {
        expect($catalogue->find($id))->not->toBeNull("{$id} has a tester but is not in the register");
    }
});

it('keeps check history for 30 days', function () {
    IntegrationCheck::query()->create([
        'integration_id' => 'stripe', 'environment' => 'live', 'outcome' => 'passed', 'message' => 'integrations.checks.passed',
        'parameters' => [], 'trigger' => 'scheduled', 'created_at' => now()->subDays(31),
    ]);
    IntegrationCheck::query()->create([
        'integration_id' => 'stripe', 'environment' => 'live', 'outcome' => 'passed', 'message' => 'integrations.checks.passed',
        'parameters' => [], 'trigger' => 'scheduled', 'created_at' => now()->subDays(29),
    ]);

    $this->artisan('model:prune', ['--model' => [IntegrationCheck::class]])->assertSuccessful();

    expect(IntegrationCheck::query()->count())->toBe(1);
});
