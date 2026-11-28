<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Brokerage\BrokerBriefs;
use App\Connections\Drivers\SkySlope\SkySlopeSimulator;
use App\Enums\OrganisationType;
use App\Features\Flags;
use App\Integrations\IntegrationMode;
use App\Messaging\Announcements;
use App\Models\Announcement;
use App\Models\AuditEvent;
use App\Models\Connection;
use App\Models\Course;
use App\Models\Deal;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\BrokerBriefReady;
use App\Notifications\NewAnnouncement;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Announcements read receipts, training, reports and the broker's morning
 * brief (A16.3, ADR 0078).
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->okoye = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $this->lone->id]);
    $this->linda = User::factory()->withTwoFactor()->withRole(Role::Broker, $this->lone)->create(['name' => 'Linda Park', 'email' => 'linda.park@demo.reos.test']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller', 'email' => 'james.miller@demo.reos.test']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->okoye)->create(['name' => 'Maria Garcia', 'email' => 'Maria.Garcia@demo.reos.test']);
});

function closedInReos(User $agent, Organisation $org, string $property, int $price, string $closedAt): Deal
{
    return Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $agent->id, 'organisation_id' => $org->id, 'source' => 'reos', 'property' => $property, 'city' => 'Katy',
        'side' => 'sell', 'stage' => 'closed', 'price' => $price, 'closed_at' => $closedAt, 'parties' => [], 'documents' => []]);
}

describe('closings report (A16.3)', function () {
    it('matches the closings in the transaction tool (the done-when)', function () {
        IntegrationSetting::query()->firstOrCreate(['integration_id' => 'skyslope'], ['mode' => IntegrationMode::Simulator]);
        $this->actingAs($this->linda)->post('/organisation/integrations/tools/skyslope/key', ['api_key' => 'AKIA-demo-1', 'api_secret' => 'shh-secret'])->assertSessionHasNoErrors();
        $connection = Connection::query()->where('integration_id', 'skyslope')->sole();

        // Nothing closed yet, in SkySlope or REOS: SkySlope still shows, with 0.
        $report = $this->actingAs($this->linda)->getJson('/api/v1/organisation/reports/closings?period=this_month')->assertOk()->json('data');
        expect($report['totals'])->toBe(['count' => 0, 'volume' => 0])
            ->and($report['tools'])->toHaveCount(1)
            ->and($report['tools'][0])->toMatchArray(['tool' => 'skyslope', 'count' => 0, 'stale' => false]);

        // James's sale closes in SkySlope and syncs.
        SkySlopeSimulator::changeInTool($connection, 'sale', 'ss-sale-1', ['status' => 'closed']);
        $this->actingAs($this->linda)->postJson("/api/v1/connections/{$connection->id}/sync")->assertAccepted();
        // Maria closes one only in REOS: a difference to square with SkySlope.
        closedInReos($this->maria, $this->okoye, '310 Bluebonnet Ln', 300000, '2026-11-20 12:00:00');

        $report = $this->actingAs($this->linda)->getJson('/api/v1/organisation/reports/closings?period=this_month')->assertOk()->json('data');
        expect($report['tools'][0])->toMatchArray(['tool' => 'skyslope', 'count' => 1, 'volume' => 389000])
            ->and($report['totals'])->toBe(['count' => 2, 'volume' => 689000])
            ->and($report['reos_only']['count'])->toBe(1)
            ->and($report['reos_only']['deals'][0])->toMatchArray(['agent' => 'Maria Garcia', 'property' => '310 Bluebonnet Ln', 'price' => 300000])
            ->and(collect($report['by_agent'])->pluck('volume', 'name')->all())->toBe(['James Miller' => 389000, 'Maria Garcia' => 300000])
            ->and(collect($report['by_team'])->pluck('count', 'name')->all())->toBe([null => 1, 'Okoye Team' => 1])
            ->and($report['by_month'])->toBe([['month' => '2026-11', 'count' => 2, 'volume' => 689000]]);

        // The website shows the same.
        $this->actingAs($this->linda)->get('/organisation/reports?period=this_month')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('organisation/reports')
            ->where('closings.tools.0.volume', 389000)
            ->where('closings.reos_only.count', 1));
    });

    it('reads periods, marks a tool not synced in a day, and keeps a team leader to their team', function () {
        closedInReos($this->james, $this->lone, '1420 Pecan St', 410000, '2026-03-02 12:00:00');
        closedInReos($this->maria, $this->okoye, '77 Elm Dr', 250000, '2025-06-10 12:00:00');

        $year = $this->actingAs($this->linda)->getJson('/api/v1/organisation/reports/closings')->assertOk()->json('data');
        expect($year['period'])->toBe('this_year')->and($year['totals']['count'])->toBe(1)->and($year['by_month'])->toHaveCount(12)
            ->and($year['tools'])->toBe([]);
        expect($this->actingAs($this->linda)->getJson('/api/v1/organisation/reports/closings?period=last_year')->json('data.totals'))->toBe(['count' => 1, 'volume' => 250000]);
        $this->actingAs($this->linda)->getJson('/api/v1/organisation/reports/closings?period=forever')->assertUnprocessable();

        $leader = User::factory()->withRole(Role::Broker, $this->okoye)->create(['name' => 'Ada Okoye']);
        $team = $this->actingAs($leader)->getJson('/api/v1/organisation/reports/closings?period=last_year')->assertOk()->json('data');
        expect($team['scope'])->toBe('team')->and(collect($team['by_agent'])->pluck('name')->all())->toBe(['Maria Garcia']);
        expect($this->actingAs($leader)->getJson('/api/v1/organisation/reports/closings')->json('data.totals.count'))->toBe(0);

        // Agents don't see reports.
        $this->actingAs($this->james)->getJson('/api/v1/organisation/reports/closings')->assertNotFound();
    });

    it('shows each assigned course\'s progress, with CE hours for CE courses', function () {
        $legal = Course::query()->create(['title' => 'Legal Update I', 'slug' => 'legal-update-1', 'type' => 'ce', 'states' => ['TX'], 'status' => 'live', 'hours' => '4']);
        $assign = fn (User $agent, ?string $due, ?string $done) => DB::table('course_assignments')->insert(['organisation_id' => $this->lone->id, 'course_id' => $legal->id, 'user_id' => $agent->id, 'due_on' => $due, 'completed_at' => $done, 'created_at' => now(), 'updated_at' => now()]);
        app(Tenancy::class)->run($this->lone->id, function () use ($assign) {
            $assign($this->james, '2026-11-01', null);
            $assign($this->maria, '2026-12-31', '2026-11-10 10:00:00');
        });

        $training = $this->actingAs($this->linda)->getJson('/api/v1/organisation/reports/training')->assertOk()->json('data.courses');
        expect($training)->toBe([['id' => $legal->id, 'title' => 'Legal Update I', 'type' => 'ce', 'ce_hours' => '4.0', 'assigned' => 2, 'done' => 1, 'overdue' => 1]]);
    });
});

describe('the broker\'s morning brief (A16.3)', function () {
    it('gathers who needs help, deals at risk, approvals and closings, with no clients in it', function () {
        closedInReos($this->james, $this->lone, '1420 Pecan St', 410000, '2026-11-20 12:00:00');
        $brief = $this->actingAs($this->linda)->getJson('/api/v1/organisation/brief')->assertOk()->json('data');

        expect($brief['brokerage'])->toBe('Lone Star Realty')
            ->and($brief['closings'])->toBe(['count' => 1, 'volume' => 410000])
            ->and($brief['approvals'])->toBe(['waiting' => 0, 'overdue' => 0])
            ->and(json_encode($brief))->not->toContain('Pecan');
        $this->actingAs($this->linda)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('brokerBrief.closings.count', 1));
        $this->actingAs($this->james)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('brokerBrief', null));
    });

    it('tells brokers on weekday mornings when something is waiting', function () {
        Notification::fake();
        $this->artisan('reos:brokerage:briefs')->assertSuccessful();
        // Maria has been quiet and has no deal: Linda hears about it.
        Notification::assertSentTo($this->linda, BrokerBriefReady::class);
        Notification::assertNotSentTo($this->james, BrokerBriefReady::class);

        // Not on a Saturday.
        expect(app(BrokerBriefs::class)->sendDue(CarbonImmutable::parse('2026-11-28 07:30', 'America/Chicago')))->toBe(0);
    });
});

describe('announcement read receipts (A16.3)', function () {
    beforeEach(function () {
        $flags = app(Flags::class);
        $flag = $flags->flag('messages');
        $flag->forceFill(['stage' => 'everyone'])->save();
        $flags->changed($flag);
    });

    it('lists who has read it and reminds the rest, once a day', function () {
        Notification::fake();
        $announcement = app(Announcements::class)->send($this->linda, 'Office closed Friday', 'Showings still run.', ['roles' => [], 'teams' => [], 'states' => []]);
        app(Announcements::class)->read($this->james, $announcement->uuid);

        $readers = $this->actingAs($this->linda)->getJson("/announcements/{$announcement->uuid}/readers")->assertOk()->json();
        expect($readers)->toMatchArray(['recipients' => 2, 'read' => 1, 'can_remind' => true])
            ->and(array_column($readers['people'], 'name'))->toBe(['Maria Garcia', 'James Miller'])
            ->and($readers['people'][0]['read_at'])->toBeNull();

        $this->actingAs($this->linda)->post("/announcements/{$announcement->uuid}/remind")->assertRedirect()->assertSessionHasNoErrors();
        Notification::assertSentTo($this->maria, NewAnnouncement::class, fn (NewAnnouncement $n) => $n->reminder);
        Notification::assertNotSentTo($this->james, NewAnnouncement::class, fn (NewAnnouncement $n) => $n->reminder);
        expect(AuditEvent::query()->where('action', 'announcement.reminded')->exists())->toBeTrue();

        // Once a day.
        $this->actingAs($this->linda, 'sanctum')->getJson("/api/v1/announcements/{$announcement->uuid}/readers")->assertJsonPath('can_remind', false);
        $this->actingAs($this->linda, 'sanctum')->postJson("/api/v1/announcements/{$announcement->uuid}/remind")->assertUnprocessable();
        $this->travel(25)->hours();
        $this->actingAs($this->linda, 'sanctum')->postJson("/api/v1/announcements/{$announcement->uuid}/remind")->assertOk()->assertJsonPath('reminded', 1);

        // Only its sender's admins.
        $this->actingAs($this->james)->getJson("/announcements/{$announcement->uuid}/readers")->assertForbidden();
        expect(Announcement::query()->sole()->reminded_at)->not->toBeNull();
    });
});
