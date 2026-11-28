<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Agent oversight (A16.1, ADR 0076): who needs help, deals at risk, and the
 * agent's profile for the broker with privacy rules.
 */

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-11-30 15:00:00');
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->okoye = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $this->lone->id]);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->ade = User::factory()->withRole(Role::Broker, $this->okoye)->withTwoFactor()->create(['name' => 'Ade Okoye']);
    // James: licensed two years ago, last here a month ago, no deals.
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller', 'last_active_at' => now()->subDays(30), 'created_at' => now()->subYears(2)]);
    Licence::query()->create(['user_id' => $this->james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active, 'licensed_on' => '2024-11-01', 'expires_on' => '2026-12-31', 'holder_name' => 'James Miller', 'verified_by' => 'simulator', 'verified_at' => now()]);
    // Priya on the Okoye Team: active today, a deal under contract with the option period ending tomorrow.
    $this->priya = User::factory()->withRole(Role::Agent, $this->okoye)->create(['name' => 'Priya Shah', 'last_active_at' => now(), 'created_at' => now()->subYear()]);
    $this->deal = Deal::query()->create([
        'uuid' => (string) Str::uuid7(), 'user_id' => $this->priya->id, 'organisation_id' => $this->okoye->id, 'source' => 'reos', 'property' => '1420 Pecan St, Katy', 'city' => 'Katy',
        'side' => 'buy', 'stage' => 'under_contract', 'parties' => [['name' => 'Elena Ruiz', 'role' => 'buyer', 'email' => 'elena@example.com', 'phone' => '2815550142']], 'documents' => [],
    ]);
    DealDate::query()->create(['deal_id' => $this->deal->id, 'kind' => 'option_period', 'due_on' => '2026-12-01']);
    Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->priya->id, 'organisation_id' => $this->okoye->id, 'source' => 'reos', 'property' => '77 Elm Dr, Katy', 'city' => 'Katy', 'side' => 'sell', 'stage' => 'active', 'parties' => [['name' => 'Tom Lee', 'role' => 'seller', 'email' => null, 'phone' => null]], 'documents' => []]);
});

describe('agent oversight (A16.1)', function () {
    it('shows the broker who needs help and which deals are at risk, without clients (the done-when)', function () {
        $board = $this->actingAs($this->linda)->getJson('/api/v1/organisation/oversight')->assertOk()->json('data');
        expect($board)->toMatchArray(['brokerage' => 'Lone Star Realty', 'scope' => 'brokerage', 'can_change' => true])
            ->and($board['counts'])->toBe(['agents' => 2, 'need_help' => 1, 'deals_at_risk' => 1])
            ->and($board['agents'][0])->toMatchArray(['name' => 'James Miller', 'signals' => ['quiet', 'no_deal', 'ce_behind']])
            ->and($board['agents'][1])->toMatchArray(['name' => 'Priya Shah', 'team' => 'Okoye Team'])
            ->and($board['at_risk'][0])->toMatchArray(['agent' => 'Priya Shah', 'stage' => 'under_contract', 'address' => '1420 Pecan St, Katy', 'reasons' => ['date_soon'], 'next_date' => ['kind' => 'option_period', 'due_on' => '2026-12-01']]);

        $profile = $this->getJson("/api/v1/organisation/oversight/agents/{$this->priya->id}")->assertOk()->json('data');
        $json = json_encode($profile);
        // Privacy: no client names, emails, phones or the active listing's address.
        expect($json)->not->toContain('Elena')->not->toContain('elena@example.com')->not->toContain('2815550142')->not->toContain('Tom Lee')->not->toContain('77 Elm Dr')
            ->and(collect($profile['deals'])->firstWhere('stage', 'active')['address'])->toBeNull()
            ->and(collect($profile['deals'])->firstWhere('stage', 'under_contract')['address'])->toBe('1420 Pecan St, Katy')
            ->and(json_encode($board))->not->toContain('Elena')->not->toContain('77 Elm Dr');
        expect(AuditEvent::query()->where('action', 'oversight.profile_viewed')->count())->toBe(1);

        $this->get('/organisation/oversight')->assertOk()->assertInertia(fn (Assert $page) => $page->component('organisation/oversight')->has('board.agents', 2));
        $this->get("/organisation/oversight/agents/{$this->james->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('organisation/oversight-agent')->where('profile.agent.name', 'James Miller'));
    });

    it('shows the agent exactly what their broker sees', function () {
        $mine = $this->actingAs($this->priya)->getJson('/api/v1/me/broker-view')->assertOk()->json('data');
        $broker = $this->actingAs($this->linda)->getJson("/api/v1/organisation/oversight/agents/{$this->priya->id}")->json('data');
        expect($mine)->toBe($broker);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/me/broker-view')->assertOk()->assertJsonPath('data', null);
    });

    it('limits a team leader to their team', function () {
        $team = $this->actingAs($this->ade)->getJson('/api/v1/organisation/oversight')->assertOk()->json('data');
        expect($team)->toMatchArray(['scope' => 'team', 'team' => 'Okoye Team', 'can_change' => false])->and(array_column($team['agents'], 'name'))->toBe(['Priya Shah']);
        $this->getJson("/api/v1/organisation/oversight/agents/{$this->james->id}")->assertNotFound();
        $this->putJson('/api/v1/organisation/oversight/settings', ['quiet_days' => 7, 'no_deal_days' => 60, 'new_days' => 90])->assertNotFound();
    });

    it('uses the brokerage\'s own thresholds, set by the designated broker', function () {
        $this->actingAs($this->linda)->putJson('/api/v1/organisation/oversight/settings', ['quiet_days' => 1, 'no_deal_days' => 60, 'new_days' => 90])->assertUnprocessable();
        $board = $this->putJson('/api/v1/organisation/oversight/settings', ['quiet_days' => 60, 'no_deal_days' => 60, 'new_days' => 90])->assertOk()->json('data');
        expect($board['settings'])->toBe(['quiet_days' => 60, 'no_deal_days' => 60, 'new_days' => 90])
            ->and(collect($board['agents'])->firstWhere('name', 'James Miller')['signals'])->not->toContain('quiet');

        $office = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create();
        $this->actingAs($office)->putJson('/api/v1/organisation/oversight/settings', ['quiet_days' => 7, 'no_deal_days' => 60, 'new_days' => 90])->assertForbidden();
    });

    it('flags new agents and missed deadlines', function () {
        $omar = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Omar Haddad', 'last_active_at' => now(), 'created_at' => now()->subDays(20)]);
        $deal = Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $omar->id, 'organisation_id' => $this->lone->id, 'source' => 'reos', 'property' => '9 Oak Ln', 'city' => 'Katy', 'side' => 'buy', 'stage' => 'under_contract', 'parties' => [], 'documents' => []]);
        DealDate::query()->create(['deal_id' => $deal->id, 'kind' => 'financing', 'due_on' => '2026-11-25']);

        $row = collect($this->actingAs($this->linda)->getJson('/api/v1/organisation/oversight')->json('data.agents'))->firstWhere('name', 'Omar Haddad');
        expect($row['signals'])->toContain('new')->toContain('deadline_missed');
        $this->actingAs($omar)->getJson('/api/v1/organisation/oversight')->assertNotFound();
    });

    it('flags a plan with past weeks still open', function () {
        $this->actingAs($this->linda)->getJson('/api/v1/organisation/oversight')->assertOk();
        $this->travel(3)->weeks();
        $row = collect($this->getJson('/api/v1/organisation/oversight')->json('data.agents'))->firstWhere('name', 'Priya Shah');
        expect($row['signals'])->toContain('plan_behind');
    });
});
