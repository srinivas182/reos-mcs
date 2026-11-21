<?php

declare(strict_types=1);

use App\Authorization\Permission;
use App\Authorization\Role;
use App\Authorization\RoleRefused;
use App\Enums\OrganisationType;
use App\Identity\DeviceInfo;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Services\Authorization\RoleCatalogue;
use App\Services\Identity\DeviceSessions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Signs a person in on a phone and returns its token. */
function phoneToken(User $user, string $device = '0f8fad5b-d9cb-469f-a165-70867728950e'): string
{
    return app(DeviceSessions::class)->issue($user, new DeviceInfo($device, 'Pixel 9', 'android'), 'phone', false)['token'];
}

function organisationFor(Role $role): Organisation
{
    $type = $role->organisationTypes()[0];

    return $type === OrganisationType::Platform
        ? Organisation::platform()
        : Organisation::factory()->ofType($type)->create();
}

describe('permissions for every role', function () {
    it('allows exactly what the role grants', function (Role $role) {
        $user = User::factory()->withTwoFactor()->withRole($role)->create();

        foreach (Permission::cases() as $permission) {
            expect(Gate::forUser($user)->allows($permission->value))
                ->toBe($role->allows($permission), "{$role->value} → {$permission->value}");
        }
    })->with(fn () => array_map(static fn (Role $role): array => [$role], Role::cases()));

    it('grants nothing in an admin role until two-step is set up', function (Role $role) {
        $user = User::factory()->withRole($role)->create();

        foreach (Permission::cases() as $permission) {
            expect(Gate::forUser($user)->allows($permission->value))->toBeFalse();
        }
    })->with(fn () => array_map(static fn (Role $role): array => [$role], array_values(array_filter(Role::cases(), static fn (Role $r): bool => $r->isAdmin()))));

    it('works in other roles without two-step', function (Role $role) {
        $user = User::factory()->withRole($role)->create();

        expect(Gate::forUser($user)->allows($role->permissions()[0]->value))->toBeTrue();
    })->with(fn () => array_map(static fn (Role $role): array => [$role], array_values(array_filter(Role::cases(), static fn (Role $r): bool => ! $r->isAdmin()))));

    it('counts a passkey as two-step', function () {
        $user = User::factory()->withRole(Role::Broker)->create();
        DB::table('passkeys')->insert([
            'user_id' => $user->id, 'name' => 'iPhone', 'credential_id' => Str::random(20),
            'credential' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        expect(Gate::forUser($user)->allows(Permission::AgentsManage->value))->toBeTrue();
    });

    it('allows nothing for someone with no role', function () {
        $user = User::factory()->create();

        expect(Gate::forUser($user)->allows(Permission::LearningStudy->value))->toBeFalse();
    });

    it('writes the catalogue to the database, again and again', function () {
        app(RoleCatalogue::class)->sync();
        app(RoleCatalogue::class)->sync();

        $superAdmin = Spatie\Permission\Models\Role::findByName('super_admin', 'web');

        expect(DB::table('roles')->count())->toBe(count(Role::cases()))
            ->and($superAdmin->permissions->pluck('name')->sort()->values()->all())
            ->toBe(collect(Permission::values())->sort()->values()->all());
    });
});

describe('the active role', function () {
    it('starts in the role with most responsibility, and switches per device', function () {
        $brokerage = Organisation::factory()->create(['name' => 'Lone Star Realty']);
        $user = User::factory()->withTwoFactor()->withRole(Role::Agent, $brokerage)->withRole(Role::Broker, $brokerage)->create();
        $phone = phoneToken($user);
        $tablet = phoneToken($user, '7c9e6679-7425-40de-944b-e07fc1f90ae7');

        $this->withToken($phone)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('active_role', ['role' => 'broker', 'organisation_id' => $brokerage->id])
            ->assertJsonPath('roles.0.role', 'broker')
            ->assertJsonPath('roles.0.admin', true)
            ->assertJsonPath('roles.1.role', 'agent')
            ->assertJsonPath('roles.1.organisation.name', 'Lone Star Realty')
            ->assertJsonPath('two_step', ['enabled' => true, 'required' => false]);

        $this->withToken($phone)->putJson('/api/v1/me/active-role', ['role' => 'agent', 'organisation_id' => $brokerage->id])
            ->assertOk()
            ->assertJsonPath('active_role.role', 'agent')
            ->assertJsonPath('permissions', ['learning.study']);

        app('auth')->forgetGuards();
        $this->withToken($phone)->getJson('/api/v1/me')->assertJsonPath('active_role.role', 'agent');

        // The tablet switches back; each device keeps its own.
        app('auth')->forgetGuards();
        $this->withToken($tablet)->putJson('/api/v1/me/active-role', ['role' => 'broker', 'organisation_id' => $brokerage->id])->assertOk();
        app('auth')->forgetGuards();
        $this->withToken($phone)->getJson('/api/v1/me')->assertJsonPath('active_role.role', 'agent');
        app('auth')->forgetGuards();
        $this->withToken($tablet)->getJson('/api/v1/me')->assertJsonPath('active_role.role', 'broker');
    });

    it('returns to the last role when signing in on a new device', function () {
        $brokerage = Organisation::factory()->create();
        $user = User::factory()->withRole(Role::Agent, $brokerage)->withRole(Role::Mentor, $brokerage)->create();

        $this->withToken(phoneToken($user))->putJson('/api/v1/me/active-role', ['role' => 'agent', 'organisation_id' => $brokerage->id])->assertOk();

        app('auth')->forgetGuards();
        $this->withToken(phoneToken($user, '7c9e6679-7425-40de-944b-e07fc1f90ae7'))->getJson('/api/v1/me')
            ->assertJsonPath('active_role.role', 'agent');
    });

    it('refuses a role the person does not hold', function () {
        $user = User::factory()->withRole(Role::Agent)->create();
        $school = Organisation::factory()->ofType(OrganisationType::School)->create();

        $this->withToken(phoneToken($user))->putJson('/api/v1/me/active-role', ['role' => 'instructor', 'organisation_id' => $school->id])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'roles.errors.notHeld');

        app('auth')->forgetGuards();
        $this->withToken(phoneToken($user))->putJson('/api/v1/me/active-role', ['role' => 'king', 'organisation_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    });

    it('lets an admin role be chosen but grants nothing until two-step', function () {
        $user = User::factory()->withRole(Role::SuperAdmin)->create();

        $this->withToken(phoneToken($user))->getJson('/api/v1/me')
            ->assertJsonPath('active_role.role', 'super_admin')
            ->assertJsonPath('permissions', [])
            ->assertJsonPath('two_step', ['enabled' => false, 'required' => true]);
    });

    it('falls back when the remembered role is removed', function () {
        $brokerage = Organisation::factory()->create();
        $user = User::factory()->withRole(Role::Agent, $brokerage)->withRole(Role::Mentor, $brokerage)->create();
        $token = phoneToken($user);
        $this->withToken($token)->putJson('/api/v1/me/active-role', ['role' => 'agent', 'organisation_id' => $brokerage->id]);

        app(Memberships::class)->revoke($user, Role::Agent, $brokerage);

        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertJsonPath('active_role.role', 'mentor');
    });
});

describe('admin screens', function () {
    it('sends an admin without two-step to set it up', function () {
        $admin = User::factory()->withRole(Role::SuperAdmin)->create();

        $this->actingAs($admin)->get('/admin/integrations')
            ->assertRedirect(route('security.edit'))
            ->assertSessionHas('status', 'two-step-required');

        $this->actingAs($admin)->getJson('/admin/integrations')
            ->assertForbidden()
            ->assertJsonPath('reason', 'roles.errors.twoStepRequired');
    });

    it('lets an admin with two-step in', function () {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/integrations')
            ->assertOk();
    });

    it('keeps everyone else out', function () {
        $broker = User::factory()->withTwoFactor()->withRole(Role::Broker)->create();

        $this->actingAs($broker)->get('/admin/integrations')->assertForbidden();
    });

    it('shares roles with the web app', function () {
        $user = User::factory()->withRole(Role::Candidate)->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.roles.held.0.role', 'candidate')
                ->where('auth.roles.held.0.organisation.type', 'school')
                ->where('auth.roles.active.role', 'candidate')
                ->where('auth.roles.permissions', ['learning.study'])
                ->where('auth.roles.twoStepRequired', false)
                ->where('auth.can.manageIntegrations', false));
    });
});

describe('granting and removing roles', function () {
    it('audits every change', function () {
        $actor = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        $school = Organisation::factory()->ofType(OrganisationType::School)->create();

        app(Memberships::class)->grant($user, Role::Instructor, $school, $actor);
        app(Memberships::class)->revoke($user, Role::Instructor, $school, $actor);

        $events = AuditEvent::query()->where('subject_id', (string) $user->id)->orderBy('id')->get();

        expect($events->pluck('action')->all())->toBe(['role.granted', 'role.removed'])
            ->and($events->first()?->actor_id)->toBe($actor->id)
            ->and($events->first()?->properties)->toBe(['role' => 'instructor', 'organisation_id' => $school->id])
            ->and(app(Memberships::class)->of($user))->toBe([]);
    });

    it('only grants a role in the right kind of organisation', function () {
        $user = User::factory()->create();

        app(Memberships::class)->grant($user, Role::Broker, Organisation::factory()->ofType(OrganisationType::School)->create());
    })->throws(RoleRefused::class, 'roles.errors.wrongOrganisation');

    it('always keeps one super admin', function () {
        $admin = User::factory()->superAdmin()->create();

        app(Memberships::class)->revoke($admin, Role::SuperAdmin, Organisation::platform());
    })->throws(RoleRefused::class, 'roles.errors.lastSuperAdmin');

    it('removes a super admin when another remains', function () {
        $first = User::factory()->superAdmin()->create();
        User::factory()->superAdmin()->create();

        app(Memberships::class)->revoke($first, Role::SuperAdmin, Organisation::platform());

        expect(app(Memberships::class)->holders(Role::SuperAdmin))->toHaveCount(1);
    });

    it('holds the same role in two organisations', function () {
        $user = User::factory()->withRole(Role::Agent, organisationFor(Role::Broker))->withRole(Role::Agent, organisationFor(Role::Broker))->create();

        expect(app(Memberships::class)->of($user))->toHaveCount(2);
    });
});

describe('policies', function () {
    it('lets people see themselves and the People screen see everyone', function () {
        $person = User::factory()->create();
        $other = User::factory()->withRole(Role::Agent)->create();
        $admin = User::factory()->superAdmin()->create();

        expect($person->can('view', $person))->toBeTrue()
            ->and($other->can('view', $person))->toBeFalse()
            ->and($other->can('viewAny', User::class))->toBeFalse()
            ->and($admin->can('view', $person))->toBeTrue()
            ->and($admin->can('manageRoles', $person))->toBeTrue()
            ->and($other->can('manageRoles', $person))->toBeFalse();
    });

    it('lets members see their organisation and its admins manage it', function () {
        $brokerage = Organisation::factory()->create();
        $elsewhere = Organisation::factory()->create();
        $agent = User::factory()->withRole(Role::Agent, $brokerage)->create();
        $broker = User::factory()->withTwoFactor()->withRole(Role::Broker, $brokerage)->create();

        expect($agent->can('view', $brokerage))->toBeTrue()
            ->and($agent->can('view', $elsewhere))->toBeFalse()
            ->and($agent->can('update', $brokerage))->toBeFalse()
            ->and($broker->can('update', $brokerage))->toBeTrue()
            ->and($broker->can('update', $elsewhere))->toBeFalse()
            ->and(User::factory()->superAdmin()->create()->can('update', $elsewhere))->toBeTrue();
    });

    it('keeps the audit log for audit.view', function () {
        expect(User::factory()->superAdmin()->create()->can('viewAny', AuditEvent::class))->toBeTrue()
            ->and(User::factory()->withTwoFactor()->withRole(Role::Broker)->create()->can('viewAny', AuditEvent::class))->toBeFalse();
    });
});
