<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\ConnectionStatus;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\Connection;
use App\Models\Organisation;
use App\Models\SupportAccessGrant;
use App\Models\User;
use App\Notifications\SupportAccessRequested;
use App\Services\Tenancy\SupportAccess;
use App\Tenancy\SupportAccessStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Support sign-in with consent (A4.1c, screen 60). */
function supportAdmin(): User
{
    return User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
}

/** @return array{0: Organisation, 1: User} Lone Star Realty and its broker, Linda. */
function supportTenant(): array
{
    $lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $linda = User::factory()->withRole(Role::Broker, $lone)->withTwoFactor()->create(['name' => 'Linda Park']);

    return [$lone, $linda];
}

function askSupport(object $test, User $admin, Organisation $tenant, string $reason = 'Linda asked us to check her calendar sync'): object
{
    return $test->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
        ->post("/admin/tenants/{$tenant->id}/support", ['reason' => $reason]);
}

/** Ask, approve as Linda, and start as support. */
function supportSession(object $test): array
{
    [$lone, $linda] = supportTenant();
    $admin = supportAdmin();
    askSupport($test, $admin, $lone)->assertRedirect();
    $grant = SupportAccessGrant::query()->sole();
    $test->actingAs($linda)->post("/support-access/{$grant->id}/approve")->assertRedirect();
    $test->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post("/admin/tenants/{$lone->id}/support/start")->assertRedirect('/dashboard');

    return [$lone, $linda, $admin, $grant->fresh()];
}

describe('asking', function () {
    it('emails every owner with the reason, audited in the tenant’s log', function () {
        Notification::fake();
        [$lone, $linda] = supportTenant();
        $second = User::factory()->withRole(Role::Broker, $lone)->create();
        $agent = User::factory()->withRole(Role::Agent, $lone)->create();

        askSupport($this, supportAdmin(), $lone)->assertRedirect();

        $grant = SupportAccessGrant::query()->sole();
        expect($grant->status)->toBe(SupportAccessStatus::Pending)->and($grant->role)->toBe(Role::Broker);
        Notification::assertSentTo([$linda, $second], SupportAccessRequested::class);
        Notification::assertNotSentTo($agent, SupportAccessRequested::class);
        expect(AuditEvent::query()->where('action', 'support.requested')->sole()->organisation_id)->toBe($lone->id);
    });

    it('needs a reason, an owner, and only one open request', function () {
        Notification::fake();
        [$lone] = supportTenant();
        $empty = Organisation::factory()->ofType(OrganisationType::Brokerage)->create();
        $admin = supportAdmin();

        askSupport($this, $admin, $lone, 'help')->assertSessionHasErrors('reason');
        askSupport($this, $admin, $empty)->assertSessionHasErrors(['reason' => __('support.errors.noOwner')]);
        askSupport($this, $admin, $lone)->assertRedirect();
        askSupport($this, $admin, $lone)->assertSessionHasErrors(['reason' => __('support.errors.alreadyOpen')]);
        expect(SupportAccessGrant::query()->count())->toBe(1);
    });

    it('writes an email the owner understands', function () {
        [$lone, $linda] = supportTenant();
        askSupport($this, supportAdmin(), $lone);

        $mail = (new SupportAccessRequested(SupportAccessGrant::query()->sole()))->toMail($linda);
        expect($mail->subject)->toBe('REOS support asks to sign in to Lone Star Realty')
            ->and(implode(' ', $mail->introLines))->toContain('Reason: Linda asked us to check her calendar sync')
            ->toContain('60 minutes at most')
            ->toContain("never see anyone's personal connections");
    });
});

describe('the owner decides', function () {
    it('shows the request to an owner only', function () {
        Notification::fake();
        [$lone, $linda] = supportTenant();
        askSupport($this, supportAdmin(), $lone);
        $grant = SupportAccessGrant::query()->sole();

        $this->actingAs($linda)->get("/support-access/{$grant->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('support-access/show')
            ->where('grant.tenant', 'Lone Star Realty')
            ->where('grant.requester', 'Ops Srini')
            ->where('grant.status', 'pending'));

        $this->actingAs(User::factory()->withRole(Role::Agent, $lone)->create())->get("/support-access/{$grant->id}")->assertNotFound();
        $this->actingAs(User::factory()->withRole(Role::Broker)->create())->post("/support-access/{$grant->id}/approve")->assertNotFound();
    });

    it('approves or declines once, audited', function () {
        Notification::fake();
        [$lone, $linda] = supportTenant();
        askSupport($this, supportAdmin(), $lone);
        $grant = SupportAccessGrant::query()->sole();

        $this->actingAs($linda)->post("/support-access/{$grant->id}/decline")->assertRedirect();
        $this->actingAs($linda)->post("/support-access/{$grant->id}/approve")->assertSessionHasErrors('grant');

        expect($grant->fresh()?->status)->toBe(SupportAccessStatus::Declined)
            ->and(AuditEvent::query()->where('action', 'support.declined')->sole()->actor_id)->toBe($linda->id);
    });

    it('needs two-step to approve, but never to decline', function () {
        Notification::fake();
        [$lone] = supportTenant();
        $owner = User::factory()->withRole(Role::Broker, $lone)->create();
        askSupport($this, supportAdmin(), $lone);
        $grant = SupportAccessGrant::query()->sole();

        $this->actingAs($owner)->get("/support-access/{$grant->id}")->assertInertia(fn (Assert $page) => $page->where('twoStep', false));
        $this->actingAs($owner)->post("/support-access/{$grant->id}/approve")->assertSessionHasErrors(['grant' => __('support.errors.twoStep')]);
        $this->actingAs($owner)->post("/support-access/{$grant->id}/decline")->assertRedirect();
        expect($grant->fresh()?->status)->toBe(SupportAccessStatus::Declined);
    });

    it('ends on its own after 24 hours without an answer', function () {
        Notification::fake();
        [$lone, $linda] = supportTenant();
        askSupport($this, supportAdmin(), $lone);
        $grant = SupportAccessGrant::query()->sole();

        $this->travel(25)->hours();
        $this->actingAs($linda)->post("/support-access/{$grant->id}/approve")->assertSessionHasErrors('grant');
        expect($grant->fresh()?->status)->toBe(SupportAccessStatus::Expired);
    });
});

describe('the support session', function () {
    beforeEach(fn () => Notification::fake());

    it('works inside the tenant as its admin, with the banner, every page logged', function () {
        [$lone, , $admin, $grant] = supportSession($this);

        expect($grant->status)->toBe(SupportAccessStatus::Active)
            ->and($grant->ends_at?->diffInMinutes($grant->started_at, true))->toEqual(60.0);

        $this->actingAs($admin)->get('/connections')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('supportSession.tenant', 'Lone Star Realty')
            ->where('auth.roles.active', ['role' => 'broker', 'organisationId' => $lone->id]));

        $viewed = AuditEvent::query()->where('action', 'support.viewed')->get();
        expect($viewed->pluck('properties.path')->all())->toContain('/connections')
            ->and($viewed->every(fn (AuditEvent $e) => $e->organisation_id === $lone->id))->toBeTrue();
    });

    it('never shows anyone’s personal connections, and keeps the back office closed', function () {
        [$lone, $linda, $admin] = supportSession($this);
        Connection::query()->create([
            'integration_id' => 'google-workspace', 'owner_type' => 'user', 'owner_id' => $linda->id, 'mode' => IntegrationMode::Simulator,
            'external_account_id' => 'linda-calendar', 'account_label' => 'linda@lonestar.example', 'scopes' => [], 'access_token' => 'x', 'status' => ConnectionStatus::Active,
        ]);

        $this->actingAs($admin)->get('/connections')->assertInertia(fn (Assert $page) => $page->has('connections', 0));
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/tenants')->assertForbidden();
    });

    it('ends after 60 minutes, back to the REOS role', function () {
        [, , $admin, $grant] = supportSession($this);

        $this->travel(61)->minutes();
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('supportSession', null)
            ->where('auth.roles.active.role', 'super_admin'));
        expect($grant->fresh()?->status)->toBe(SupportAccessStatus::Expired);
    });

    it('ends when support ends it, or when the owner does', function () {
        [$lone, $linda, $admin, $grant] = supportSession($this);

        $this->actingAs($linda)->post("/support-access/{$grant->id}/revoke")->assertRedirect();
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('supportSession', null));
        expect($grant->fresh()?->status)->toBe(SupportAccessStatus::Revoked);

        // A fresh request, started and then ended by support from the banner.
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post("/admin/tenants/{$lone->id}/support", ['reason' => 'Following up on the calendar sync']);
        $next = SupportAccessGrant::query()->latest('id')->first();
        $this->actingAs($linda)->post("/support-access/{$next->id}/approve");
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post("/admin/tenants/{$lone->id}/support/start");
        $this->actingAs($admin)->delete('/support-session')->assertRedirect("/admin/tenants/{$lone->id}");

        expect($next->fresh()?->status)->toBe(SupportAccessStatus::Ended)
            ->and(AuditEvent::query()->where('action', 'support.ended')->pluck('properties')->pluck('how')->all())->toBe(['revoked', 'ended']);
    });

    it('starts only for the person who asked, after a yes', function () {
        [$lone, $linda] = supportTenant();
        $admin = supportAdmin();
        askSupport($this, $admin, $lone);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post("/admin/tenants/{$lone->id}/support/start")->assertSessionHasErrors('grant');

        $grant = SupportAccessGrant::query()->sole();
        $this->actingAs($linda)->post("/support-access/{$grant->id}/approve");
        $this->actingAs(supportAdmin())->withSession(['auth.password_confirmed_at' => time()])->post("/admin/tenants/{$lone->id}/support/start")->assertSessionHasErrors('grant');
        expect($grant->fresh()?->status)->toBe(SupportAccessStatus::Approved);
    });

    it('picks the tenant’s own admin role', function () {
        expect(SupportAccess::roleFor(OrganisationType::Association))->toBe(Role::AssociationAdmin)
            ->and(SupportAccess::roleFor(OrganisationType::School))->toBe(Role::SchoolAdmin)
            ->and(SupportAccess::roleFor(OrganisationType::Team))->toBe(Role::Broker);
    });
});
