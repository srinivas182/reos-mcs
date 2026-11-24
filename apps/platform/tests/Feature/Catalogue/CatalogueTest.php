<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Catalogue\CatalogueStatus;
use App\Connections\ConnectionStatus;
use App\Connections\SyncTrigger;
use App\Enums\OrganisationType;
use App\Identity\DeviceInfo;
use App\Integrations\Catalogue;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\CatalogueSetting;
use App\Models\Connection;
use App\Models\IntegrationInterest;
use App\Models\Organisation;
use App\Models\ToolRequest;
use App\Models\User;
use App\Services\Catalogue\IntegrationCatalogue;
use App\Services\Connections\SyncEngine;
use App\Services\Identity\DeviceSessions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function catalogueAgent(?Organisation $brokerage = null): User
{
    return User::factory()->withRole(Role::Agent, $brokerage)->create();
}

function onPhone(object $test, User $user): object
{
    app('auth')->forgetGuards();

    return $test->withToken(app(DeviceSessions::class)->issue($user, new DeviceInfo((string) Str::uuid(), 'iPhone 17', 'ios'), 'phone', false)['token']);
}

/** @return array<string, array<string, mixed>> */
function libraryOf(object $test, User $user): array
{
    return collect(onPhone($test, $user)->getJson('/api/v1/catalogue')->assertOk()->json('data'))->keyBy('id')->all();
}

function setCatalogue(string $id, array $changes): void
{
    app(IntegrationCatalogue::class)->update(app(Catalogue::class)->find($id), $changes, User::factory()->create());
}

function catalogueAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

describe('the people’s library (/api/v1/catalogue)', function () {
    it('lists the tools people connect, grouped, with what they can do', function () {
        $library = libraryOf($this, catalogueAgent());

        expect($library)->toHaveKeys(['google-calendar', 'follow-up-boss', 'zillow-leads', 'phone-contacts'])
            ->not->toHaveKeys(['stripe', 'stripe-connect', 'mls-har', 'sentry', 'google-sign-in'])
            ->and($library['google-calendar'])->toMatchArray(['group' => 'calendar', 'status' => 'enabled', 'action' => 'connect', 'accounts' => 'user'])
            ->and($library['dotloop']['group'])->toBe('deals')
            ->and($library['zillow-leads'])->toMatchArray(['group' => 'leads', 'status' => 'coming_soon', 'action' => 'notify'])
            ->and($library['linkedin']['group'])->toBe('more');
    });

    it('never offers Connect for a tool whose connector isn’t built yet', function () {
        $library = libraryOf($this, catalogueAgent());

        expect($library['zillow-leads'])->toMatchArray(['status' => 'coming_soon', 'action' => 'notify'])
            // Built (B11.2) and a beta: joined by an organisation first.
            ->and($library['linkedin'])->toMatchArray(['status' => 'beta', 'action' => 'join_beta']);
    });

    it('changes at once when the super admin switches a tool to coming soon (no app release)', function () {
        $agent = catalogueAgent();
        expect(libraryOf($this, $agent)['google-calendar']['action'])->toBe('connect');

        setCatalogue('google-calendar', ['status' => CatalogueStatus::ComingSoon]);

        expect(libraryOf($this, $agent)['google-calendar'])->toMatchArray(['status' => 'coming_soon', 'action' => 'notify']);
        onPhone($this, $agent)->postJson('/api/v1/connections', ['integration' => 'google-calendar'])->assertStatus(422);
    });

    it('can hide coming-soon tools until release', function () {
        setCatalogue('zillow-leads', ['show_coming_soon' => false]);

        expect(libraryOf($this, catalogueAgent()))->not->toHaveKey('zillow-leads');
    });

    it('shows a tool only in its states and to its roles', function () {
        setCatalogue('google-calendar', ['states' => ['LA']]);
        setCatalogue('outlook-calendar', ['roles' => ['broker']]);
        $agent = catalogueAgent();

        expect(libraryOf($this, $agent))->not->toHaveKeys(['google-calendar', 'outlook-calendar']);

        setCatalogue('google-calendar', ['states' => ['TX', 'LA']]);
        expect(libraryOf($this, $agent))->toHaveKey('google-calendar');
    });

    it('lets beta organisations connect and others ask to join', function () {
        $lonestar = Organisation::factory()->ofType(OrganisationType::Brokerage)->create();
        setCatalogue('google-calendar', ['status' => CatalogueStatus::Beta, 'beta_organisations' => [$lonestar->id]]);

        expect(libraryOf($this, catalogueAgent($lonestar))['google-calendar'])->toMatchArray(['status' => 'beta', 'action' => 'connect'])
            ->and(libraryOf($this, catalogueAgent())['google-calendar'])->toMatchArray(['status' => 'beta', 'action' => 'join_beta']);
    });

    it('lets an organisation hide a tool from its people, never enable one', function () {
        $lonestar = Organisation::factory()->ofType(OrganisationType::Brokerage)->create();
        $catalogue = app(IntegrationCatalogue::class);
        $catalogue->hide($lonestar, 'google-calendar', true, User::factory()->create());

        expect(libraryOf($this, catalogueAgent($lonestar)))->not->toHaveKey('google-calendar')
            ->and(libraryOf($this, catalogueAgent()))->toHaveKey('google-calendar');

        setCatalogue('zoom', ['tenants_may_hide' => false]);
        $catalogue->hide($lonestar, 'zoom', true, User::factory()->create());
        expect(libraryOf($this, catalogueAgent($lonestar)))->toHaveKey('zoom');
    });

    it('shows a retiring tool with its last day, then removes it', function () {
        setCatalogue('google-calendar', ['status' => CatalogueStatus::Retired, 'retires_on' => now()->addMonth()->toDateString()]);
        expect(libraryOf($this, catalogueAgent())['google-calendar'])->toMatchArray(['status' => 'retired', 'action' => 'none', 'retires_on' => now()->addMonth()->toDateString()]);

        setCatalogue('google-calendar', ['retires_on' => now()->subDay()->toDateString()]);
        expect(libraryOf($this, catalogueAgent()))->not->toHaveKey('google-calendar');
    });

    it('counts Notify me as demand, once per person, and can be undone', function () {
        $agent = catalogueAgent();

        onPhone($this, $agent)->postJson('/api/v1/catalogue/zillow-leads/interest')->assertNoContent();
        onPhone($this, $agent)->postJson('/api/v1/catalogue/zillow-leads/interest')->assertNoContent();
        expect(IntegrationInterest::query()->count())->toBe(1)
            ->and(libraryOf($this, $agent)['zillow-leads']['interested'])->toBeTrue()
            ->and(app(IntegrationCatalogue::class)->demand())->toBe(['zillow-leads' => 1]);

        onPhone($this, $agent)->postJson('/api/v1/catalogue/google-calendar/interest')->assertStatus(422);
        onPhone($this, $agent)->postJson('/api/v1/catalogue/nonsense/interest')->assertStatus(422);

        onPhone($this, $agent)->deleteJson('/api/v1/catalogue/zillow-leads/interest')->assertNoContent();
        expect(IntegrationInterest::query()->count())->toBe(0);
    });

    it('takes "My tool isn’t listed" requests', function () {
        $agent = catalogueAgent();

        onPhone($this, $agent)->postJson('/api/v1/tool-requests', ['name' => 'Sierra Interactive', 'note' => 'Our leads are there.'])->assertCreated();
        onPhone($this, $agent)->postJson('/api/v1/tool-requests', ['name' => 'x'])->assertJsonValidationErrors('name');

        expect(ToolRequest::query()->sole()->only(['name', 'note']))->toBe(['name' => 'Sierra Interactive', 'note' => 'Our leads are there.']);
    });

    it('needs a sign-in', function () {
        $this->getJson('/api/v1/catalogue')->assertUnauthorized();
    });
});

describe('the kill switch', function () {
    it('pauses syncing and connecting, keeps the data, and shows "Temporarily unavailable"', function () {
        $agent = catalogueAgent();
        $connection = Connection::query()->create([
            'integration_id' => 'google-calendar', 'owner_type' => 'user', 'owner_id' => $agent->id, 'mode' => IntegrationMode::Simulator,
            'external_account_id' => 'sim-1', 'scopes' => [], 'access_token' => 'x', 'status' => ConnectionStatus::Active,
        ]);

        setCatalogue('google-calendar', ['status' => CatalogueStatus::Paused]);

        expect(libraryOf($this, $agent)['google-calendar'])->toMatchArray(['status' => 'paused', 'action' => 'none', 'connected' => 1])
            ->and(app(SyncEngine::class)->run($connection, 'events', SyncTrigger::Scheduled))->toBe('skipped')
            ->and(Connection::query()->count())->toBe(1);
    });
});

describe('the super admin catalogue (web journey screens 64–65)', function () {
    it('lists integrations with status, availability, connections and demand', function () {
        IntegrationInterest::query()->create(['integration_id' => 'zillow-leads', 'user_id' => catalogueAgent()->id, 'kind' => 'notify']);
        setCatalogue('google-calendar', ['states' => ['TX']]);

        $this->actingAs(catalogueAdmin())->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/integrations')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/integrations/index')
            ->where('integrations', fn ($rows) => collect($rows)->firstWhere('id', 'google-calendar')['catalogue']['states'] === ['TX']
                && collect($rows)->firstWhere('id', 'zillow-leads')['catalogue']['demand'] === 1
                && collect($rows)->firstWhere('id', 'sentry')['catalogue'] === null));
    });

    it('shows each tool in tabs, with availability first', function () {
        $admin = catalogueAdmin();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/integrations/google-calendar')->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'availability')
            ->where('catalogue.effective', 'enabled')
            ->where('catalogue.built', true)
            ->has('catalogue.options.roles')
            ->has('health'));
        $this->get('/admin/integrations/google-calendar/health')->assertInertia(fn (Assert $page) => $page->where('tab', 'health'));
        $this->get('/admin/integrations/sentry')->assertInertia(fn (Assert $page) => $page->where('tab', 'credentials')->where('catalogue', null));
    });

    it('saves availability rules, audited', function () {
        $admin = catalogueAdmin();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->put('/admin/integrations/zoom/catalogue', [
                'status' => 'beta', 'states' => ['tx'], 'roles' => ['agent'], 'beta_organisations' => [],
                'tenants_may_hide' => true, 'tenants_may_require' => false, 'show_coming_soon' => true,
            ])->assertSessionHasErrors('states.0');

        $this->put('/admin/integrations/zoom/catalogue', [
            'status' => 'beta', 'states' => ['TX'], 'roles' => ['agent'], 'beta_organisations' => [],
            'tenants_may_hide' => true, 'tenants_may_require' => true, 'show_coming_soon' => true,
        ])->assertRedirect();

        $setting = CatalogueSetting::query()->findOrFail('zoom');
        expect($setting->status)->toBe(CatalogueStatus::Beta)
            ->and($setting->states)->toBe(['TX'])
            ->and($setting->tenants_may_require)->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'catalogue.updated')->latest('id')->first()?->properties['to'])->toBe('beta');

        $this->put('/admin/integrations/zoom/catalogue', ['status' => 'retired', 'tenants_may_hide' => true, 'tenants_may_require' => false, 'show_coming_soon' => true])
            ->assertSessionHasErrors('retires_on');
    });

    it('pauses and resumes with the kill switch', function () {
        $this->actingAs(catalogueAdmin())->withSession(['auth.password_confirmed_at' => time()])
            ->put('/admin/integrations/google-calendar/pause', ['paused' => true])->assertRedirect();
        expect(CatalogueSetting::query()->findOrFail('google-calendar')->status)->toBe(CatalogueStatus::Paused);

        $this->put('/admin/integrations/google-calendar/pause', ['paused' => false]);
        expect(CatalogueSetting::query()->findOrFail('google-calendar')->status)->toBeNull();
    });

    it('shows Notify-me demand and tool requests', function () {
        $agent = catalogueAgent();
        IntegrationInterest::query()->create(['integration_id' => 'zillow-leads', 'user_id' => $agent->id, 'kind' => 'notify']);
        ToolRequest::query()->create(['user_id' => $agent->id, 'name' => 'Sierra Interactive']);

        $this->actingAs(catalogueAdmin())->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/integrations/demand')->assertInertia(fn (Assert $page) => $page
            ->component('admin/integrations/demand')
            ->where('tools.0', ['id' => 'zillow-leads', 'name' => 'Zillow leads', 'notify' => 1, 'beta' => 0])
            ->where('requests.0.name', 'Sierra Interactive'));
    });

    it('is closed to everyone else', function () {
        $this->actingAs(catalogueAgent())->withSession(['auth.password_confirmed_at' => time()])
            ->put('/admin/integrations/zoom/pause', ['paused' => true])->assertForbidden();
        $this->get('/admin/integrations/demand')->assertForbidden();
    });
});
