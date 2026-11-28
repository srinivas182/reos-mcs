<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Brokerage\CeCompliance;
use App\Brokerage\Sponsorships;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\CeCompletion;
use App\Models\Course;
use App\Models\Deal;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\RegulatorRecord;
use App\Models\User;
use App\Notifications\CeBrokerReminder;
use App\Notifications\CourseAssigned;
use App\Notifications\SponsorshipChanged;
use App\Services\Authorization\Memberships;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * CE compliance and sponsorship (A16.2, ADR 0077).
 */

function txLicence(User $agent, string $number, string $expires, bool $first): void
{
    Licence::query()->create(['user_id' => $agent->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => $number, 'status' => LicenceStatus::Active,
        'licensed_on' => $first ? '2025-01-15' : '2020-01-15', 'expires_on' => $expires, 'first_renewal' => $first, 'holder_name' => $agent->name, 'verified_by' => 'simulator', 'verified_at' => now()]);
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-11-30 15:00:00');
    $this->artisan('reos:regulators:import')->assertSuccessful();
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'states' => ['TX'], 'trec_number' => '9012345']);
    $this->okoye = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $this->lone->id]);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
    $this->priya = User::factory()->withRole(Role::Agent, $this->okoye)->create(['name' => 'Priya Shah']);
    $this->dana = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Dana Brooks']);
    // James renews in a month with 18 CE hours to do; Priya's first renewal (SAE) is in four months; Dana's is next year.
    txLicence($this->james, '0798765', '2026-12-31', false);
    txLicence($this->priya, '0812345', '2027-03-31', true);
    txLicence($this->dana, '0711111', '2027-10-31', false);
});

describe('CE compliance (A16.2)', function () {
    it('reminds all due agents in one action (the done-when)', function () {
        $table = $this->actingAs($this->linda)->getJson('/api/v1/organisation/ce')->assertOk()->json('data');
        $rows = collect($table['rows'])->keyBy('name');
        expect($rows['James Miller'])->toMatchArray(['status' => 'behind', 'sae' => false, 'due_on' => '2026-12-31'])
            ->and($rows['James Miller']['hours_left'])->toBeGreaterThan(0)
            ->and($rows['Priya Shah'])->toMatchArray(['status' => 'behind', 'sae' => true, 'team' => 'Okoye Team'])
            ->and($rows['Dana Brooks']['status'])->toBe('on_track')
            ->and($table['counts'])->toMatchArray(['agents' => 3, 'due' => 2]);

        $sent = $this->postJson('/api/v1/organisation/ce/remind')->assertOk()->json('data');
        expect($sent)->toMatchArray(['sent' => 2, 'skipped' => 0]);
        Notification::assertSentTo($this->james, CeBrokerReminder::class, fn (CeBrokerReminder $n) => $n->broker === 'Linda Park' && ! $n->sae);
        Notification::assertSentTo($this->priya, CeBrokerReminder::class, fn (CeBrokerReminder $n) => $n->sae);
        Notification::assertNotSentTo($this->dana, CeBrokerReminder::class);

        // Once a week at most.
        $this->postJson('/api/v1/organisation/ce/remind')->assertOk()->assertJsonPath('data.sent', 0)->assertJsonPath('data.skipped', 2);
        $this->travel(8)->days();
        $this->postJson('/api/v1/organisation/ce/remind')->assertOk()->assertJsonPath('data.sent', 2);
        expect(AuditEvent::query()->where('action', 'ce.reminded')->count())->toBe(3);
        $this->get('/organisation/ce')->assertOk()->assertInertia(fn (Assert $page) => $page->component('organisation/ce-compliance')->has('table.rows', 3)->has('sponsorships.agents', 3));
    });

    it('assigns a course to everyone due, once, and marks it done when completed', function () {
        $course = Course::query()->create(['title' => 'Legal Update I', 'slug' => 'legal-update-1', 'type' => 'ce', 'states' => ['TX'], 'status' => 'live', 'hours' => '4']);
        $done = $this->actingAs($this->linda)->postJson('/api/v1/organisation/ce/assign', ['course_id' => $course->id, 'due' => true, 'due_on' => '2026-12-20'])->assertOk()->json('data');
        expect($done['assigned'])->toBe(2);
        Notification::assertSentTo($this->james, CourseAssigned::class, fn (CourseAssigned $n) => $n->course === 'Legal Update I' && $n->dueOn === '2026-12-20');
        $this->postJson('/api/v1/organisation/ce/assign', ['course_id' => $course->id, 'agents' => [$this->james->id, $this->dana->id]])->assertOk()->assertJsonPath('data.assigned', 1);

        CeCompletion::query()->create(['user_id' => $this->james->id, 'state_code' => 'TX', 'title' => 'Legal Update I', 'hours' => '4.0', 'source' => 'reos', 'completed_on' => '2026-12-01', 'course_id' => $course->id]);
        expect(app(CeCompliance::class)->markCompleted($this->james))->toBe(1);
        $rows = collect($this->getJson('/api/v1/organisation/ce')->json('data.rows'))->keyBy('name');
        expect($rows['James Miller']['assignments'])->toBe([['course' => 'Legal Update I', 'due_on' => '2026-12-20', 'done' => true]]);

        $this->postJson('/api/v1/organisation/ce/assign', ['course_id' => 999999, 'due' => true])->assertUnprocessable()->assertJsonValidationErrors('course');
    });

    it('needs the "sponsor agents" duty, and limits a team leader to their team', function () {
        $office = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create();
        $this->actingAs($office)->getJson('/api/v1/organisation/ce')->assertForbidden();
        $ade = User::factory()->withRole(Role::Broker, $this->okoye)->withTwoFactor()->create(['name' => 'Ade Okoye']);
        $this->actingAs($this->linda)->putJson("/api/v1/organisation/staff/{$ade->id}", ['duties' => ['sponsor_agents']])->assertOk();
        $team = $this->actingAs($ade)->getJson('/api/v1/organisation/ce')->assertOk()->json('data');
        expect(array_column($team['rows'], 'name'))->toBe(['Priya Shah']);
        $this->getJson('/api/v1/organisation/sponsorships')->assertNotFound();
    });
});

describe('sponsorship (A16.2)', function () {
    it('releases an agent with a handover of open deals, and confirms with TREC', function () {
        $deal = Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'organisation_id' => $this->lone->id, 'source' => 'reos', 'property' => '1420 Pecan St, Katy', 'side' => 'buy', 'stage' => 'under_contract', 'parties' => [], 'documents' => []]);

        $this->actingAs($this->linda)->postJson('/api/v1/organisation/sponsorships/release', ['agent_id' => $this->james->id, 'effective_on' => '2026-11-30'])->assertUnprocessable()->assertJsonValidationErrors('handover_to');
        $this->postJson('/api/v1/organisation/sponsorships/release', ['agent_id' => $this->james->id, 'effective_on' => '2027-02-01', 'handover_to' => $this->dana->id])->assertJsonValidationErrors('effective_on');
        $board = $this->postJson('/api/v1/organisation/sponsorships/release', ['agent_id' => $this->james->id, 'effective_on' => '2026-11-30', 'handover_to' => $this->dana->id])->assertOk()->json('data');

        expect($deal->refresh()->user_id)->toBe($this->dana->id)
            ->and(app(Memberships::class)->find($this->james->refresh(), Role::Agent, $this->lone->id))->toBeNull()
            ->and($board['changes'][0])->toMatchArray(['kind' => 'release', 'agent' => 'James Miller', 'handover_to' => 'Dana Brooks', 'deals' => 1, 'status' => 'recorded']);
        Notification::assertSentTo($this->james, SponsorshipChanged::class, fn (SponsorshipChanged $n) => $n->what === 'released');
        Notification::assertSentTo($this->dana, SponsorshipChanged::class, fn (SponsorshipChanged $n) => $n->what === 'handover' && $n->deals === 1);

        // TREC's file still lists James under Linda's licence: waiting.
        DB::table('sponsorship_changes')->update(['status' => 'done']);
        $this->artisan('reos:sponsorships')->assertSuccessful();
        expect(DB::table('sponsorship_changes')->value('status'))->toBe('done');

        RegulatorRecord::query()->where('number_digits', '798765')->update(['sponsor_number' => null, 'sponsor_name' => null]);
        $this->artisan('reos:sponsorships')->assertSuccessful();
        expect(DB::table('sponsorship_changes')->value('status'))->toBe('confirmed')
            ->and(AuditEvent::query()->where('action', 'sponsorship.released')->count())->toBe(1);
    });

    it('ends the role on a future release date, and confirms a join against TREC', function () {
        $this->actingAs($this->linda)->postJson('/api/v1/organisation/sponsorships/release', ['agent_id' => $this->dana->id, 'effective_on' => '2026-12-10'])->assertOk();
        expect(app(Memberships::class)->find($this->dana, Role::Agent, $this->lone->id))->not->toBeNull();
        $this->travelTo('2026-12-10 15:00:00');
        $this->artisan('reos:sponsorships')->assertSuccessful();
        expect(app(Memberships::class)->find($this->dana->refresh(), Role::Agent, $this->lone->id))->toBeNull();

        // James joins (an accepted invitation): TREC lists him under Lone Star's licence, so it's confirmed.
        app(Sponsorships::class)->joined($this->james, $this->lone);
        $this->artisan('reos:sponsorships')->assertSuccessful();
        expect(DB::table('sponsorship_changes')->where('kind', 'join')->value('status'))->toBe('confirmed');
    });
});
