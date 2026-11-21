<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Identity\WebDevice;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** A super admin with two-step who confirmed their password just now. */
function peopleAdmin(object $test): User
{
    $admin = User::factory()->superAdmin()->create(['name' => 'REOS Operations']);
    $test->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

describe('People screen', function () {
    it('finds people by name, email or phone digits', function () {
        peopleAdmin($this);
        User::factory()->create(['name' => 'Sofia Reyes', 'email' => 'sofia@example.test']);
        User::factory()->create(['name' => 'James Miller', 'email' => 'james@example.test', 'phone' => '+17135550188']);

        $this->get('/settings/people?search=SOFIA')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/people/index')
                ->where('search', 'SOFIA')
                ->has('people.data', 1)
                ->where('people.data.0.name', 'Sofia Reyes'));

        $this->get('/settings/people?search=0188')
            ->assertInertia(fn (Assert $page) => $page->where('people.data.0.name', 'James Miller'));

        $this->get('/settings/people?search=50%25')
            ->assertInertia(fn (Assert $page) => $page->has('people.data', 0));

        $this->get('/settings/people')
            ->assertInertia(fn (Assert $page) => $page->where('people.total', 3));
    });

    it('shows a person with their roles, what can be granted and recent changes', function () {
        $admin = peopleAdmin($this);
        $school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
        $person = User::factory()->create(['name' => 'Rachel Hughes']);
        app(Memberships::class)->grant($person, Role::Instructor, $school, $admin);
        app(AuditLogger::class)->record('identity.signed_in', 'user', (string) $person->id);

        $this->get("/settings/people/{$person->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/people/show')
                ->where('person.name', 'Rachel Hughes')
                ->where('person.roles.0.role', 'instructor')
                ->where('person.roles.0.organisation.name', 'Texas Realty School')
                ->where('person.two_step', false)
                ->has('roleOptions', count(Role::cases()))
                ->where('roleOptions.0.role', 'candidate')
                ->where('roleOptions.0.organisationTypes', ['school', 'platform'])
                ->has('activity', 1)
                ->where('activity.0.action', 'role.granted')
                ->where('activity.0.actor', 'REOS Operations'));
    });

    it('grants and removes roles, audited with who did it', function () {
        $admin = peopleAdmin($this);
        $brokerage = Organisation::factory()->create(['name' => 'Lone Star Realty']);
        $person = User::factory()->create();

        $this->post("/settings/people/{$person->id}/roles", ['role' => 'agent', 'organisation_id' => $brokerage->id])
            ->assertRedirect();
        expect(app(Memberships::class)->find($person, Role::Agent, $brokerage->id))->not->toBeNull();

        $this->delete("/settings/people/{$person->id}/roles", ['role' => 'agent', 'organisation_id' => $brokerage->id])
            ->assertRedirect();
        expect(app(Memberships::class)->of($person))->toBe([]);

        expect(AuditEvent::query()->where('subject_id', (string) $person->id)->pluck('actor_id', 'action')->all())
            ->toBe(['role.granted' => $admin->id, 'role.removed' => $admin->id]);
    });

    it('refuses a role in the wrong kind of organisation, and the last super admin', function () {
        $admin = peopleAdmin($this);
        $school = Organisation::factory()->ofType(OrganisationType::School)->create();
        $person = User::factory()->create();

        $this->from("/settings/people/{$person->id}")
            ->post("/settings/people/{$person->id}/roles", ['role' => 'broker', 'organisation_id' => $school->id])
            ->assertRedirect("/settings/people/{$person->id}")
            ->assertSessionHasErrors(['role' => "That role can't be held in this kind of organisation."]);

        $this->post("/settings/people/{$person->id}/roles", ['role' => 'king', 'organisation_id' => 999])
            ->assertSessionHasErrors(['role', 'organisation_id']);

        $this->delete("/settings/people/{$admin->id}/roles", ['role' => 'super_admin', 'organisation_id' => Organisation::platform()->id])
            ->assertSessionHasErrors('role');
        expect(app(Memberships::class)->holders(Role::SuperAdmin))->toHaveCount(1);
    });

    it('is for super admins with two-step only', function () {
        $person = User::factory()->create();

        $this->actingAs(User::factory()->withTwoFactor()->withRole(Role::Broker)->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/settings/people')
            ->assertForbidden();

        $this->actingAs(User::factory()->withTwoFactor()->withRole(Role::Broker)->create())
            ->post("/settings/people/{$person->id}/roles", ['role' => 'agent', 'organisation_id' => Organisation::platform()->id])
            ->assertForbidden();

        $this->actingAs(User::factory()->withRole(Role::SuperAdmin)->create())
            ->get('/settings/people')
            ->assertRedirect(route('security.edit'));

        $this->get('/settings/people')->assertRedirect(route('security.edit'));
        auth()->logout();
        $this->get('/settings/people')->assertRedirect(route('login'));
    });

    it('asks a super admin to confirm their password first', function () {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/settings/people')
            ->assertRedirect();
    });
});

describe('web role switcher', function () {
    it('switches role and this browser remembers it', function () {
        $brokerage = Organisation::factory()->create();
        $user = User::factory()->withTwoFactor()->withRole(Role::Broker, $brokerage)->withRole(Role::Agent, $brokerage)->create();
        $device = (string) Str::uuid();
        DB::table('known_devices')->insert([
            'user_id' => $user->id, 'device_id' => $device, 'name' => 'Chrome · macOS', 'platform' => 'web',
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $this->actingAs($user)->withCookie(WebDevice::COOKIE, $device)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('auth.roles.active.role', 'broker'));

        $this->put('/settings/active-role', ['role' => 'agent', 'organisation_id' => $brokerage->id])
            ->assertRedirect(route('dashboard'));

        $this->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.roles.active.role', 'agent')
                ->where('auth.roles.permissions', ['learning.study']));

        expect(DB::table('known_devices')->where('device_id', $device)->value('active_role'))->toBe('agent');
    });

    it('refuses a role the person does not hold', function () {
        $user = User::factory()->withRole(Role::Agent)->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->put('/settings/active-role', ['role' => 'super_admin', 'organisation_id' => Organisation::platform()->id])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('role');
    });

    it('shares People in Settings with super admins only', function () {
        $this->actingAs(User::factory()->superAdmin()->create())->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.managePeople', true)
                ->where('auth.roles.twoStepEnabled', true));

        $this->actingAs(User::factory()->withRole(Role::Agent)->create())->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.managePeople', false));
    });
});
