<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Brokerage\BrokerageSetup;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Services\Tenancy\Invitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Bring in your agents, and seats and who pays (A15.1b, ADR 0070).
 */

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-11-28 09:00:00');
    // TREC (Simulator): Bayou City Homes LLC (0600123) sponsors Maria Lopez (0722222) and Omar Haddad (0733333).
    $this->artisan('reos:regulators:import')->assertSuccessful();
    $this->bayou = Organisation::factory()->create(['name' => 'Bayou City Homes', 'type' => OrganisationType::Brokerage, 'trec_number' => '0600123', 'limits' => ['seats' => 25]]);
    $this->grace = User::factory()->withTwoFactor()->create(['name' => 'Grace Hill']);
    app(Memberships::class)->grant($this->grace, Role::Broker, $this->bayou, $this->grace);
    $this->omar = User::factory()->create(['name' => 'Omar Haddad', 'email' => 'omar@example.com']);
    Licence::query()->create(['user_id' => $this->omar->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0733333', 'status' => LicenceStatus::Active, 'holder_name' => 'Omar Haddad']);
});

describe('bring in your agents (A15.1b)', function () {
    it('invites every sponsored agent on TREC in one step (the done-when)', function () {
        $roster = $this->actingAs($this->grace)->getJson('/api/v1/organisation/roster')->assertOk()->json('data');
        expect($roster['counts'])->toBe(['sponsored' => 2, 'member' => 0, 'invited' => 0, 'on_reos' => 1, 'contact' => 0, 'needs_contact' => 1])
            ->and(collect($roster['agents'])->pluck('state', 'name')->all())->toBe(['Maria Lopez' => 'needs_contact', 'Omar Haddad' => 'on_reos'])
            // An agent's own account details are never shown.
            ->and(collect($roster['agents'])->firstWhere('name', 'Omar Haddad')['contact'])->toBeNull();

        // TREC has no emails: the broker adds Maria's. James isn't sponsored by Bayou City Homes.
        $added = $this->postJson('/api/v1/organisation/roster/contacts', ['lines' => "0722222, maria@example.com\n0798765, james@example.com"])->assertOk()->json('data');
        expect($added['saved'])->toBe(1)->and($added['refused'])->toBe(['0798765, james@example.com'])
            ->and($added['roster']['counts']['contact'])->toBe(1);

        $sent = $this->postJson('/api/v1/organisation/roster/invite')->assertOk()->json('data');
        expect($sent)->toMatchArray(['sent' => 2, 'needs_contact' => 0])->and($sent['roster']['counts']['invited'])->toBe(2)
            ->and(Invitation::query()->pluck('email')->sort()->values()->all())->toBe(['maria@example.com', 'omar@example.com'])
            ->and(Invitation::query()->first()?->expires_at->toDateString())->toBe('2026-12-12');

        // Inviting again sends nothing twice.
        $this->postJson('/api/v1/organisation/roster/invite')->assertJsonPath('data.sent', 0);

        // Omar accepts: he's in the brokerage.
        $invitations = app(Invitations::class);
        $invitations->accept(Invitation::query()->where('email', 'omar@example.com')->sole(), $this->omar);
        $this->getJson('/api/v1/organisation/roster')->assertJsonPath('data.counts.member', 1);
        $this->get('/agents')->assertInertia(fn (Assert $page) => $page->where('trec.counts.sponsored', 2));
    });

    it('is for the brokerage\'s own brokers only', function () {
        $team = Organisation::factory()->create(['type' => OrganisationType::Team, 'parent_id' => $this->bayou->id]);
        $leader = User::factory()->create();
        app(Memberships::class)->grant($leader, Role::Broker, $team, $this->grace);
        $this->actingAs($leader)->getJson('/api/v1/organisation/roster')->assertNotFound();
        $this->actingAs($this->omar)->getJson('/api/v1/organisation/roster')->assertNotFound();
        $this->putJson('/api/v1/organisation/seats', ['payer' => 'member', 'credits_pooled' => true])->assertNotFound();
    });
});

describe('seats and who pays (A15.1b)', function () {
    it('lets the broker choose who pays and pool AI credits', function () {
        $seats = $this->actingAs($this->grace)->getJson('/api/v1/organisation/seats')->assertOk()->json('data');
        expect($seats)->toMatchArray(['seats' => 25, 'payer' => 'organisation', 'credits_pooled' => true, 'credits_per_seat' => 300, 'pool' => 7500, 'chosen' => false]);

        $this->putJson('/api/v1/organisation/seats', ['payer' => 'split', 'credits_pooled' => false])->assertOk()
            ->assertJsonPath('data.payer', 'split')->assertJsonPath('data.pool', null)->assertJsonPath('data.chosen', true);
        expect($this->bayou->refresh()->tenantLimits()->payer)->toBe('split')
            ->and(collect(app(BrokerageSetup::class)->for($this->bayou)['steps'])->firstWhere('key', 'seats')['done'])->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'tenant.seats_chosen')->count())->toBe(1);
        $this->putJson('/api/v1/organisation/seats', ['payer' => 'nobody', 'credits_pooled' => true])->assertUnprocessable();
        $this->get('/organisation/settings')->assertInertia(fn (Assert $page) => $page->where('seatsPlan.payer', 'split'));
    });
});
