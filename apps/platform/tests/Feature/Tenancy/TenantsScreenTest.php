<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Identity\DeviceInfo;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\State;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Services\Identity\DeviceSessions;
use App\Tenancy\TenantFeature;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Tenants and Tenant detail (A4.1b, web journey screens 59 and 60). */
function tenantsAdmin(object $test): User
{
    $admin = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
    $test->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

function brokerage(string $name, array $attributes = []): Organisation
{
    return Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => $name, 'states' => ['TX'], ...$attributes]);
}

beforeEach(function () {
    State::query()->firstOrCreate(['code' => 'TX'], ['name' => 'Texas', 'status' => 'live']);
    State::query()->firstOrCreate(['code' => 'OK'], ['name' => 'Oklahoma', 'status' => 'planned']);
});

describe('Tenants (screen 59)', function () {
    it('lists every tenant but REOS itself, with people, domain, cell and status', function () {
        $lone = brokerage('Lone Star Realty', ['limits' => ['seats' => 142]]);
        User::factory()->count(3)->withRole(Role::Agent, $lone)->create();
        Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $lone->id]);
        tenantsAdmin($this);

        $this->get('/admin/tenants')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/tenants/index')
            ->has('tenants', 2)
            ->where('tenants.0.name', 'Lone Star Realty')
            ->where('tenants.0.members', 3)
            ->where('tenants.0.seats', 142)
            ->where('tenants.0.domain', 'lone-star-realty.reos.com')
            ->where('tenants.0.cell', 'cell-1')
            ->where('tenants.0.status', 'active')
            ->where('tenants.1.type', 'team')
            ->where('tenants.1.parent', 'Lone Star Realty')
            ->where('tenants.1.domain', null)
            ->where('tenants.1.plan', 'inherits'));
    });

    it('filters by type, status, state and name', function () {
        brokerage('Lone Star Realty');
        brokerage('Bayou Realty', ['status' => TenantStatus::Trial, 'trial_ends_at' => now()->addDays(14), 'states' => ['OK']]);
        Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School', 'states' => ['TX']]);
        tenantsAdmin($this);

        $names = fn (string $query) => collect($this->get('/admin/tenants?'.$query)->viewData('page')['props']['tenants'])->pluck('name')->all();

        expect($names('type=school'))->toBe(['Texas Realty School'])
            ->and($names('status=trial'))->toBe(['Bayou Realty'])
            ->and($names('state=OK'))->toBe(['Bayou Realty'])
            ->and($names('search=lone'))->toBe(['Lone Star Realty'])
            ->and($names('type=nonsense'))->toHaveCount(3);
    });

    it('shows days left on a trial', function () {
        brokerage('Bayou Realty', ['status' => TenantStatus::Trial, 'trial_ends_at' => now()->addDays(14)->endOfDay()]);
        tenantsAdmin($this);

        $this->get('/admin/tenants')->assertInertia(fn (Assert $page) => $page->where('tenants.0.trial_days_left', 15));
    });

    it('exports the table as CSV, safe to open in a spreadsheet', function () {
        brokerage('=HYPERLINK("http://evil.example")');
        tenantsAdmin($this);

        $csv = $this->get('/admin/tenants/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();

        expect($csv)->toStartWith('id,name,type')
            ->and($csv)->toContain("'=HYPERLINK")
            ->and($csv)->not->toContain(',=HYPERLINK');
    });

    it('creates a tenant with its own address, audited', function () {
        $admin = tenantsAdmin($this);

        $this->post('/admin/tenants', ['name' => 'Gulf Coast Homes', 'type' => 'brokerage', 'states' => ['TX'], 'plan' => 'brokerage', 'seats' => 40, 'trial_days' => 14])
            ->assertRedirect();

        $tenant = Organisation::query()->where('name', 'Gulf Coast Homes')->sole();
        expect($tenant->slug)->toBe('gulf-coast-homes')
            ->and($tenant->status)->toBe(TenantStatus::Trial)
            ->and($tenant->tenantLimits()->seats)->toBe(40);

        $event = AuditEvent::query()->where('action', 'tenant.created')->sole();
        expect($event->organisation_id)->toBe($tenant->id)->and($event->actor_id)->toBe($admin->id);
    });

    it('puts a team inside a brokerage', function () {
        $lone = brokerage('Lone Star Realty');
        $school = Organisation::factory()->ofType(OrganisationType::School)->create();
        tenantsAdmin($this);

        $this->post('/admin/tenants', ['name' => 'Okoye Team', 'type' => 'team', 'states' => ['TX'], 'plan' => 'inherits'])->assertSessionHasErrors('parent_id');
        $this->post('/admin/tenants', ['name' => 'Okoye Team', 'type' => 'team', 'parent_id' => $school->id, 'states' => ['TX'], 'plan' => 'inherits'])->assertSessionHasErrors('parent_id');
        $this->post('/admin/tenants', ['name' => 'Okoye Team', 'type' => 'team', 'parent_id' => $lone->id, 'states' => ['TX'], 'plan' => 'inherits'])->assertRedirect();
        $this->post('/admin/tenants', ['name' => 'Nope', 'type' => 'platform', 'states' => ['TX'], 'plan' => 'brokerage'])->assertSessionHasErrors('type');
        $this->post('/admin/tenants', ['name' => 'Nope', 'type' => 'brokerage', 'states' => ['ZZ'], 'plan' => 'brokerage'])->assertSessionHasErrors('states.0');
    });

    it('is only for REOS super admins who confirmed their password', function () {
        $broker = User::factory()->withRole(Role::Broker)->withTwoFactor()->create();
        $this->actingAs($broker)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/tenants')->assertForbidden();

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => null])->get('/admin/tenants')->assertRedirect();
    });
});

describe('Tenant detail (screen 60)', function () {
    it('shows the owner, people against seats, features and limits', function () {
        $lone = brokerage('Lone Star Realty', ['limits' => ['seats' => 10]]);
        User::factory()->withRole(Role::Broker, $lone)->create(['name' => 'Linda Park', 'email' => 'linda@lonestar.example']);
        User::factory()->count(2)->withRole(Role::Agent, $lone)->create();
        tenantsAdmin($this);

        $this->get("/admin/tenants/{$lone->id}/features")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/tenants/show')
            ->where('tab', 'features')
            ->where('tenant.owner.name', 'Linda Park')
            ->where('tenant.members', 3)
            ->where('tenant.limits.seats', 10)
            ->where('tenant.limits.rate_limit', 600)
            ->where('tenant.features', fn ($features) => collect($features)->firstWhere('key', 'virtual_broker')['on'] === true
                && collect($features)->firstWhere('key', 'recruiting_agents')['availability'] === 'beta'));
    });

    it('never opens REOS itself as a tenant', function () {
        tenantsAdmin($this);
        $this->get('/admin/tenants/'.Organisation::platform()->id)->assertNotFound();
    });

    it('saves features, limits, plan and retention, audited with before and after', function () {
        $lone = brokerage('Lone Star Realty');
        tenantsAdmin($this);

        $this->patch("/admin/tenants/{$lone->id}", [
            'plan' => 'enterprise',
            'features' => ['ai_video_lessons' => true, 'virtual_broker' => false],
            'limits' => ['seats' => 142, 'rate_limit' => 900, 'credits_pooled' => false, 'log_retention_years' => 7],
        ])->assertRedirect();

        $lone->refresh();
        expect($lone->hasFeature(TenantFeature::AiVideoLessons))->toBeTrue()
            ->and($lone->hasFeature(TenantFeature::VirtualBroker))->toBeFalse()
            ->and($lone->tenantLimits()->seats)->toBe(142)
            ->and($lone->tenantLimits()->rateLimit)->toBe(900)
            ->and($lone->tenantLimits()->creditsPooled)->toBeFalse();

        $event = AuditEvent::query()->where('action', 'tenant.updated')->sole();
        expect($event->properties['changed'])->toBe(['plan', 'features', 'limits'])
            ->and($event->properties['before']['plan'])->toBeNull()
            ->and($event->properties['after']['plan'])->toBe('enterprise')
            ->and($event->organisation_id)->toBe($lone->id);

        $this->get("/admin/tenants/{$lone->id}/audit")->assertInertia(fn (Assert $page) => $page
            ->has('audit', 1)->where('audit.0.action', 'tenant.updated')->where('audit.0.actor', 'Ops Srini'));
    });

    it('refuses beta switches, short retention and unknown limits', function () {
        $lone = brokerage('Lone Star Realty');
        tenantsAdmin($this);

        $this->patch("/admin/tenants/{$lone->id}", ['features' => ['recruiting_agents' => true]])->assertSessionHasErrors('features');
        $this->patch("/admin/tenants/{$lone->id}", ['limits' => ['log_retention_years' => 1]])->assertSessionHasErrors('limits.log_retention_years');
        $this->patch("/admin/tenants/{$lone->id}", ['limits' => ['shell' => 'rm']])->assertSessionHasErrors('limits');
        expect(AuditEvent::query()->where('action', 'tenant.updated')->exists())->toBeFalse();
    });

    it('suspends with a reason, which takes away its people’s access, and restores', function () {
        $lone = brokerage('Lone Star Realty');
        $agent = User::factory()->withRole(Role::Agent, $lone)->create();
        tenantsAdmin($this);

        $this->put("/admin/tenants/{$lone->id}/status", ['status' => 'suspended'])->assertSessionHasErrors('reason');
        $this->put("/admin/tenants/{$lone->id}/status", ['status' => 'suspended', 'reason' => 'Unpaid since October'])->assertRedirect();

        expect($lone->refresh()->status)->toBe(TenantStatus::Suspended)
            ->and($lone->status_reason)->toBe('Unpaid since October')
            ->and(app(Memberships::class)->of($agent))->toBe([]);

        $this->put("/admin/tenants/{$lone->id}/status", ['status' => 'active'])->assertRedirect();
        expect($lone->refresh()->status_reason)->toBeNull()
            ->and(app(Memberships::class)->of($agent))->toHaveCount(1)
            ->and(AuditEvent::query()->where('action', 'tenant.status_changed')->pluck('properties')->pluck('to')->all())->toBe(['suspended', 'active']);
    });
});

describe('the API limit is per tenant (screen 60)', function () {
    it('holds a tenant to its own requests per minute', function () {
        $lone = brokerage('Lone Star Realty', ['limits' => ['rate_limit' => 60]]);
        $agent = User::factory()->withRole(Role::Agent, $lone)->create();
        $token = app(DeviceSessions::class)->issue($agent, new DeviceInfo((string) Str::uuid(), 'iPhone 17', 'ios'), 'phone', false)['token'];

        for ($i = 0; $i < 60; $i++) {
            $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        }

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(429);
    });
});
