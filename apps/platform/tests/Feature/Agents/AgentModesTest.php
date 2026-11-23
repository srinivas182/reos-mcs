<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Models\AgentGoalChange;
use App\Models\Deal;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-24 10:00:00');
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    $this->licence = Licence::query()->create([
        'user_id' => $this->james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active,
        'holder_name' => 'James Miller', 'licensed_on' => '2026-04-01', 'sponsor_name' => 'Linda Park', 'verified_by' => 'trec-licence', 'verified_at' => now(),
    ]);
});

function closedDeal(User $user, string $closedAt, string $source = 'lone-wolf'): Deal
{
    return Deal::query()->create([
        'uuid' => (string) Str::uuid7(), 'user_id' => $user->id, 'source' => $source, 'property' => '1 Main St', 'side' => 'sell', 'stage' => 'closed',
        'closed_at' => $closedAt, 'parties' => [], 'documents' => [],
    ]);
}

describe('agent modes (A11.1a)', function () {
    it('welcomes a newly licensed agent with Launch suggested and Own locked until four years', function () {
        $this->actingAs($this->james)->getJson('/api/v1/agent/mode')->assertOk()
            ->assertJsonPath('data.goal', null)->assertJsonPath('data.needs_setup', true)->assertJsonPath('data.suggested_default', 'launch')
            ->assertJsonPath('data.own', ['unlocked' => false, 'unlocks_on' => '2030-04-01', 'years' => 4])
            ->assertJsonPath('data.welcome', ['name' => 'James Miller', 'licence' => '0798765', 'state' => 'TX', 'brokerage' => 'Linda Park', 'verified' => true]);

        $this->putJson('/api/v1/agent/mode', ['goal' => 'own'])->assertUnprocessable()->assertJsonValidationErrors('goal');
        $this->putJson('/api/v1/agent/mode', ['goal' => 'launch', 'reason' => 'setup'])->assertOk()->assertJsonPath('data.goal', 'launch')->assertJsonPath('data.needs_setup', false);
        $this->putJson('/api/v1/agent/mode/flags', ['flags' => ['part_time', 'bilingual']])->assertOk()->assertJsonPath('data.flags', ['part_time', 'bilingual']);
        $this->putJson('/api/v1/agent/mode/flags', ['flags' => ['astronaut']])->assertUnprocessable();

        expect(AgentGoalChange::query()->sole())->from_goal->toBeNull()->to_goal->toBe('launch')->reason->toBe('setup');
    });

    it('opens Own with a broker licence or four years licensed', function () {
        $this->licence->forceFill(['licensed_on' => '2022-06-01'])->save();
        $this->actingAs($this->james)->getJson('/api/v1/agent/mode')->assertJsonPath('data.own.unlocked', true)->assertJsonPath('data.suggested_default', 'grow');
        $this->putJson('/api/v1/agent/mode', ['goal' => 'own'])->assertOk()->assertJsonPath('data.goal', 'own');

        $linda = User::factory()->withRole(Role::Agent)->create();
        Licence::query()->create(['user_id' => $linda->id, 'state_code' => 'TX', 'type' => LicenceType::Broker, 'number' => '9012345', 'status' => LicenceStatus::Active, 'licensed_on' => '2026-01-01', 'verified_by' => 'trec-licence', 'verified_at' => now()]);
        $this->actingAs($linda)->getJson('/api/v1/agent/mode')->assertJsonPath('data.own.unlocked', true);
    });

    it('suggests Grow after the first verified closing and Lead at the state\'s count, and "Not yet" waits 30 days', function () {
        $this->actingAs($this->james)->putJson('/api/v1/agent/mode', ['goal' => 'launch', 'reason' => 'setup'])->assertJsonPath('data.suggestion', null);

        // A deal typed into REOS isn't a verified closing.
        closedDeal($this->james, '2026-11-01', 'reos');
        $this->getJson('/api/v1/agent/mode')->assertJsonPath('data.suggestion', null);

        closedDeal($this->james, '2026-11-10');
        $this->getJson('/api/v1/agent/mode')->assertJsonPath('data.suggestion', ['goal' => 'grow', 'closings' => 1]);
        $this->postJson('/api/v1/agent/mode/snooze')->assertJsonPath('data.suggestion', null);
        $this->travel(31)->days();
        $this->getJson('/api/v1/agent/mode')->assertJsonPath('data.suggestion.goal', 'grow');

        $this->putJson('/api/v1/agent/mode', ['goal' => 'grow', 'reason' => 'suggested'])->assertJsonPath('data.suggestion', null);
        foreach (range(1, 11) as $i) {
            closedDeal($this->james, now()->subDays($i * 20)->toDateString());
        }
        $this->getJson('/api/v1/agent/mode')->assertJsonPath('data.suggestion', ['goal' => 'lead', 'closings' => 12])
            ->assertJsonPath('data.history.0.reason', 'suggested')->assertJsonPath('data.history.0.from', 'launch');
    });

    it('is for agents only, and shows on the dashboard and in Settings', function () {
        $candidate = User::factory()->withRole(Role::Candidate, Organisation::platform())->create();
        $this->actingAs($candidate)->getJson('/api/v1/agent/mode')->assertNotFound();
        $this->get('/settings/goal')->assertNotFound();

        $this->actingAs($this->james)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('mode.needs_setup', true));
        $this->get('/agent/setup')->assertOk()->assertInertia(fn (Assert $page) => $page->component('agent/setup')->where('mode.suggested_default', 'launch'));
        $this->put('/agent/mode', ['goal' => 'grow', 'reason' => 'setup'], ['Accept' => 'application/json'])->assertOk();
        $this->get('/settings/goal')->assertOk()->assertInertia(fn (Assert $page) => $page->component('settings/goal')->where('mode.goal', 'grow'));
    });
});
