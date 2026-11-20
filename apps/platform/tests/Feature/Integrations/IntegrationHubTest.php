<?php

declare(strict_types=1);

use App\Integrations\Catalogue;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\IntegrationCredential;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** A super admin who confirmed their password a moment ago. */
function admin(): User
{
    return User::factory()->superAdmin()->create(['name' => 'REOS Operations']);
}

function confirmed(): array
{
    return ['auth.password_confirmed_at' => time()];
}

const STRIPE_LIVE = [
    'publishable_key' => 'example-publishable-key-0001',
    'secret_key' => 'sk_live_fake-0000-0000-9876',
    'webhook_secret' => 'example-webhook-secret-4321',
];

describe('access', function () {
    it('sends guests to sign in', function () {
        $this->get(route('integrations.index'))->assertRedirect(route('login'));
    });

    it('is closed to everyone except super admins', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(confirmed())->get(route('integrations.index'))->assertForbidden();
        $this->actingAs($user)->withSession(confirmed())->get(route('integrations.show', 'stripe'))->assertForbidden();
        $this->actingAs($user)->withSession(confirmed())
            ->put(route('integrations.keys.update', ['stripe', 'live']), ['values' => STRIPE_LIVE])
            ->assertForbidden();
        $this->actingAs($user)->withSession(confirmed())
            ->patch(route('integrations.mode.update', 'stripe'), ['mode' => 'off'])
            ->assertForbidden();

        expect(IntegrationCredential::query()->count())->toBe(0);
    });

    it('asks the super admin to confirm their password first', function () {
        $this->actingAs(admin())->get(route('integrations.index'))->assertRedirect(route('password.confirm'));
    });

    it('tells the web app who may see the Integrations menu', function () {
        $this->actingAs(admin())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.manageIntegrations', true));

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.manageIntegrations', false));
    });

    it('returns not found for services that are not in the register', function () {
        $this->actingAs(admin())->withSession(confirmed())
            ->get(route('integrations.show', 'not-a-vendor'))
            ->assertNotFound();

        $this->actingAs(admin())->withSession(confirmed())
            ->put('/settings/integrations/stripe/keys/production', ['values' => STRIPE_LIVE])
            ->assertNotFound();
    });
});

describe('the list', function () {
    it('shows every connectable service from the register, on demo data by default', function () {
        $catalogue = app(Catalogue::class)->all();

        $this->actingAs(admin())->withSession(confirmed())
            ->get(route('integrations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/integrations/index')
                ->has('integrations', count($catalogue))
                ->where('integrations.0.id', 'google-sign-in')
                ->where('integrations.0.mode', 'simulator')
                ->where('integrations.0.keys', ['sandbox' => false, 'live' => false])
                ->where('integrations.0.needs_keys', true));

        expect(collect($catalogue)->pluck('id'))->toContain('sentry', 'posthog', 'grafana-otel', 'docusign', 'mls-har')
            ->not->toContain('github', 'apple-developer');
    });
});

describe('keys', function () {
    it('encrypts keys with their own key and never sends secrets back', function () {
        $admin = admin();

        $this->actingAs($admin)->withSession(confirmed())
            ->from(route('integrations.show', 'stripe'))
            ->put(route('integrations.keys.update', ['stripe', 'live']), ['values' => STRIPE_LIVE])
            ->assertRedirect(route('integrations.show', 'stripe'))
            ->assertSessionHasNoErrors();

        $payload = IntegrationCredential::query()->sole()->payload;
        expect($payload)->not->toContain('fake-0000-0000-9876')
            ->and(fn () => decrypt($payload, false))->toThrow(DecryptException::class)
            ->and(app(CredentialVault::class)->get('stripe', CredentialEnvironment::Live))->toBe(STRIPE_LIVE);

        $this->actingAs($admin)->withSession(confirmed())
            ->get(route('integrations.show', 'stripe'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/integrations/show')
                ->where('integration.keys.live', true)
                ->where('integration.credentials.live.publishable_key', ['saved' => true, 'value' => 'example-publishable-key-0001', 'last4' => null])
                ->where('integration.credentials.live.secret_key', ['saved' => true, 'value' => null, 'last4' => '9876'])
                ->where('integration.credentials.sandbox.secret_key', ['saved' => false, 'value' => null, 'last4' => null]))
            ->assertDontSee('sk_live_fake-0000-0000-9876', false);
    });

    it('records which fields changed and who changed them, never the values', function () {
        $admin = admin();

        $this->actingAs($admin)->withSession(confirmed())
            ->put(route('integrations.keys.update', ['stripe', 'live']), ['values' => STRIPE_LIVE]);

        $event = AuditEvent::query()->sole();
        expect($event->action)->toBe('integration.credentials_updated')
            ->and($event->subject_id)->toBe('stripe')
            ->and($event->actor_id)->toBe($admin->id)
            ->and($event->properties)->toBe(['environment' => 'live', 'fields' => ['publishable_key', 'secret_key', 'webhook_secret']])
            ->and(json_encode($event->getAttributes()))->not->toContain('fake-0000-0000-9876');
    });

    it('keeps a saved secret when the field is left blank', function () {
        $admin = admin();
        $this->actingAs($admin)->withSession(confirmed())
            ->put(route('integrations.keys.update', ['stripe', 'live']), ['values' => STRIPE_LIVE]);

        $this->actingAs($admin)->withSession(confirmed())
            ->put(route('integrations.keys.update', ['stripe', 'live']), ['values' => [
                'publishable_key' => 'example-publishable-key-0002',
                'secret_key' => '',
                'webhook_secret' => null,
            ]])
            ->assertSessionHasNoErrors();

        expect(app(CredentialVault::class)->get('stripe', CredentialEnvironment::Live))->toBe([
            ...STRIPE_LIVE,
            'publishable_key' => 'example-publishable-key-0002',
        ])->and(AuditEvent::query()->latest('id')->first()?->properties['fields'])->toBe(['publishable_key']);
    });

    it('requires every required field and rejects fields the register does not list', function () {
        $this->actingAs(admin())->withSession(confirmed())
            ->put(route('integrations.keys.update', ['stripe', 'sandbox']), ['values' => ['publishable_key' => 'example-publishable-key']])
            ->assertSessionHasErrors(['values.secret_key', 'values.webhook_secret']);

        $this->actingAs(admin())->withSession(confirmed())
            ->put(route('integrations.keys.update', ['stripe', 'sandbox']), ['values' => [...STRIPE_LIVE, 'password' => 'x']])
            ->assertSessionHasErrors('values');

        expect(IntegrationCredential::query()->count())->toBe(0);
    });

    it('checks addresses are web addresses', function () {
        $this->actingAs(admin())->withSession(confirmed())
            ->put(route('integrations.keys.update', ['sentry', 'live']), ['values' => ['dsn' => 'not a url']])
            ->assertSessionHasErrors('values.dsn');
    });

    it('takes no keys for services that use public data', function () {
        $this->actingAs(admin())->withSession(confirmed())
            ->put(route('integrations.keys.update', ['trec-licence', 'live']), ['values' => ['api_key' => 'x']])
            ->assertSessionHasErrors('values');
    });

    it('goes back to demo data when the keys it was running on are removed', function () {
        $admin = admin();
        $this->actingAs($admin)->withSession(confirmed())
            ->put(route('integrations.keys.update', ['stripe', 'live']), ['values' => STRIPE_LIVE]);
        Http::fake(['api.stripe.com/*' => Http::response(['available' => []])]);
        $this->actingAs($admin)->withSession(confirmed())
            ->post(route('integrations.checks.store', ['stripe', 'live']));
        $this->actingAs($admin)->withSession(confirmed())
            ->patch(route('integrations.mode.update', 'stripe'), ['mode' => 'live'])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->withSession(confirmed())
            ->delete(route('integrations.keys.destroy', ['stripe', 'live']))
            ->assertRedirect();

        expect(IntegrationCredential::query()->count())->toBe(0)
            ->and(IntegrationSetting::query()->find('stripe')?->mode)->toBe(IntegrationMode::Simulator)
            ->and(AuditEvent::query()->orderBy('id')->pluck('action')->all())->toBe([
                'integration.credentials_updated',
                'integration.checked',
                'integration.mode_changed',
                'integration.credentials_removed',
                'integration.mode_changed',
            ]);
    });
});

describe('how it runs', function () {
    it('needs test keys before the test system and live keys before going live', function () {
        $admin = admin();

        $this->actingAs($admin)->withSession(confirmed())
            ->patch(route('integrations.mode.update', 'stripe'), ['mode' => 'sandbox'])
            ->assertSessionHasErrors(['mode' => 'Add test keys first.']);
        $this->actingAs($admin)->withSession(confirmed())
            ->patch(route('integrations.mode.update', 'stripe'), ['mode' => 'live'])
            ->assertSessionHasErrors(['mode' => 'Add live keys first.']);

        $this->actingAs($admin)->withSession(confirmed())
            ->put(route('integrations.keys.update', ['stripe', 'sandbox']), ['values' => STRIPE_LIVE]);
        $this->actingAs($admin)->withSession(confirmed())
            ->patch(route('integrations.mode.update', 'stripe'), ['mode' => 'sandbox'])
            ->assertSessionHasNoErrors();

        expect(IntegrationSetting::query()->find('stripe')?->mode)->toBe(IntegrationMode::Sandbox)
            ->and(AuditEvent::query()->latest('id')->first()?->properties)->toBe(['from' => 'simulator', 'to' => 'sandbox']);
    });

    it('can always switch to off or demo data', function () {
        $this->actingAs(admin())->withSession(confirmed())
            ->patch(route('integrations.mode.update', 'stripe'), ['mode' => 'off'])
            ->assertSessionHasNoErrors();

        expect(IntegrationSetting::query()->find('stripe')?->mode)->toBe(IntegrationMode::Off);
    });

    it('offers no test system for public-data services, which can go live without keys', function () {
        $admin = admin();

        $this->actingAs($admin)->withSession(confirmed())
            ->patch(route('integrations.mode.update', 'trec-licence'), ['mode' => 'sandbox'])
            ->assertSessionHasErrors(['mode' => 'This service has no test system.']);
        $this->actingAs($admin)->withSession(confirmed())
            ->patch(route('integrations.mode.update', 'trec-licence'), ['mode' => 'live'])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->withSession(confirmed())
            ->get(route('integrations.show', 'trec-licence'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('integration.mode', 'live')
                ->where('integration.redirect_url', null)
                ->where('integration.modes.2', ['value' => 'sandbox', 'available' => false, 'reason' => 'integrations.mode.noTestSystem'])
                ->where('activity.0.action', 'integration.mode_changed')
                ->where('activity.0.actor', 'REOS Operations'));
    });

    it('shows the redirect address for services that sign in with OAuth', function () {
        $this->actingAs(admin())->withSession(confirmed())
            ->get(route('integrations.show', 'google-workspace'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('integration.redirect_url', url('integrations/google-workspace/callback'))
                ->where('integration.accounts', 'user'));
    });

    it('rejects modes that do not exist', function () {
        $this->actingAs(admin())->withSession(confirmed())
            ->patch(route('integrations.mode.update', 'stripe'), ['mode' => 'production'])
            ->assertSessionHasErrors('mode');
    });
});
