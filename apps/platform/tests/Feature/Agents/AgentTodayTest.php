<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Models\AgentPlan;
use App\Models\AgentPlanTask;
use App\Models\CalendarEvent;
use App\Models\CeCompletion;
use App\Models\Contact;
use App\Models\ContactActivity;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\Licence;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tuesday 24 November 2026, 10:00 in Texas.
    $this->travelTo('2026-11-24 16:00:00');
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    Licence::query()->create([
        'user_id' => $this->james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active,
        'holder_name' => 'James Miller', 'licensed_on' => '2026-04-01', 'verified_by' => 'trec-licence', 'verified_at' => now(),
    ]);
    $this->actingAs($this->james)->putJson('/api/v1/agent/mode', ['goal' => 'launch', 'reason' => 'setup'])->assertOk();
});

function todayContact(User $user, string $first = 'Maria'): Contact
{
    return Contact::query()->create(['uuid' => (string) Str::uuid7(), 'owner_type' => 'user', 'owner_id' => $user->id, 'first_name' => $first, 'last_name' => 'Lopez']);
}

describe('Today, the 90-day plan and the scorecard (A11.1b)', function () {
    it('ranks to-dos from the CRM, deal deadlines, appointments and plan tasks, each with its source', function () {
        $maria = todayContact($this->james);
        $overdue = Todo::query()->create(['user_id' => $this->james->id, 'title' => 'Call Maria back', 'due_date' => '2026-11-22', 'source' => 'crm', 'related_label' => 'Maria Lopez']);
        DB::table('contact_tasks')->insert(['contact_id' => $maria->id, 'todo_id' => $overdue->id, 'source' => 'follow-up-boss', 'external_id' => 't1', 'created_at' => now(), 'updated_at' => now()]);
        $deal = Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'source' => 'dotloop', 'property' => '1420 Pecan St', 'side' => 'buy', 'stage' => 'under_contract', 'parties' => [], 'documents' => []]);
        $option = Todo::query()->create(['user_id' => $this->james->id, 'title' => 'Option period ends', 'due_date' => '2026-11-26', 'source' => 'deal']);
        DealDate::query()->create(['deal_id' => $deal->id, 'kind' => 'option_period', 'due_on' => '2026-11-26', 'todo_id' => $option->id]);
        Todo::query()->create(['user_id' => $this->james->id, 'title' => 'Next month', 'due_date' => '2026-12-20']);
        Todo::query()->create(['user_id' => $this->james->id, 'title' => 'Already done', 'due_date' => '2026-11-24', 'done_at' => now()]);
        CalendarEvent::query()->create(['user_id' => $this->james->id, 'source' => 'reos', 'kind' => 'showing', 'title' => 'Showing with the Nguyens', 'starts_at' => '2026-11-24 21:30:00', 'ends_at' => '2026-11-24 22:30:00', 'all_day' => false, 'status' => 'confirmed']);

        $today = $this->getJson('/api/v1/agent/today')->assertOk()->json('data');

        expect(array_column($today['items'], 'reason'))->toBe(['overdue', 'deadline', 'appointment', 'plan', 'plan', 'plan'])
            ->and($today['items'][0])->toMatchArray(['title' => 'Call Maria back', 'source' => 'Follow Up Boss', 'todo_id' => $overdue->id])
            ->and($today['items'][1])->toMatchArray(['kind' => 'deadline', 'source' => 'dotloop', 'detail' => '1420 Pecan St', 'deal' => $deal->uuid])
            ->and($today['items'][2])->toMatchArray(['kind' => 'event', 'source' => 'REOS'])
            ->and($today['items'][3])->toMatchArray(['kind' => 'plan', 'source' => '90-day plan'])
            ->and($today['more'])->toBe(1)
            ->and(array_column($today['stats'], 'key'))->toBe(['week', 'conversations', 'appointments'])
            ->and($today['stats'][0])->toBe(['key' => 'week', 'value' => 1, 'target' => 13])
            ->and($today['day_one'])->toHaveCount(3)
            ->and(json_encode($today))->not->toContain('Next month')->not->toContain('Already done');

        // Done in REOS is done at the source (the CRM task follows its to-do).
        $this->post("/today/todos/{$overdue->id}/done")->assertOk();
        expect($overdue->refresh()->done_at)->not->toBeNull();
        $this->delete("/today/todos/{$overdue->id}/done")->assertOk();
        expect($overdue->refresh()->done_at)->toBeNull();
    });

    it('drops day one at 20 contacts and ticks import_contacts itself', function () {
        foreach (range(1, 20) as $n) {
            todayContact($this->james, "Contact {$n}");
        }

        $today = $this->getJson('/api/v1/agent/today')->assertOk();
        $today->assertJsonPath('data.day_one', null);
        $tasks = collect($this->getJson('/api/v1/agent/plan')->json('data.tasks'))->keyBy('code');
        expect($tasks['import_contacts'])->toMatchArray(['done' => true, 'auto' => true])
            ->and($tasks['connect_tools'])->toMatchArray(['done' => false, 'auto' => true]);
    });

    it('builds the 90-day plan for the goal, ticks tasks for their week and records the promise', function () {
        $plan = $this->getJson('/api/v1/agent/plan')->assertOk()->json('data');

        expect($plan)->toMatchArray(['goal' => 'launch', 'week' => 1, 'weeks' => 13, 'starts_on' => '2026-11-24', 'ends_on' => '2027-02-23', 'promised' => false, 'done' => 0])
            ->and(array_column($plan['phases'], 'code'))->toBe(['setup', 'conversations', 'appointments', 'contract'])
            ->and($plan['phases'][0]['current'])->toBeTrue()
            ->and(array_column($plan['tasks'], 'code'))->toBe(['connect_tools', 'import_contacts', 'announce_licence', 'first_ce_lesson'])
            ->and($plan['targets'])->toBe(['conversations' => 20, 'appointments' => 2, 'clients' => 1, 'ce_hours' => 1]);

        $this->putJson('/api/v1/agent/plan/tasks/announce_licence', ['week' => 1, 'done' => true])->assertOk()->assertJsonPath('data.done', 1);
        $this->putJson('/api/v1/agent/plan/tasks/announce_licence', ['week' => 1, 'done' => true])->assertOk()->assertJsonPath('data.done', 1);
        $this->putJson('/api/v1/agent/plan/tasks/call_sphere', ['week' => 1, 'done' => true])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson('/api/v1/agent/plan/tasks/announce_licence', ['week' => 14, 'done' => true])->assertUnprocessable()->assertJsonValidationErrors('week');
        $this->postJson('/api/v1/agent/plan/promise')->assertOk()->assertJsonPath('data.promised', true);

        // Three weeks on: Conversations, with call_sphere for this week only.
        $this->travel(15)->days();
        $plan = $this->getJson('/api/v1/agent/plan')->json('data');
        expect($plan['week'])->toBe(3)->and($plan['phases'][1]['current'])->toBeTrue()
            ->and(collect($plan['tasks'])->where('code', 'call_sphere')->values()->all())->toBe([['code' => 'call_sphere', 'week' => 3, 'done' => false, 'auto' => false, 'repeats' => true, 'link' => 'contacts']]);
        $this->putJson('/api/v1/agent/plan/tasks/call_sphere', ['week' => 3, 'done' => true])->assertOk();
        $this->putJson('/api/v1/agent/plan/tasks/call_sphere', ['week' => 3, 'done' => false])->assertOk();
        expect(AgentPlanTask::query()->pluck('code')->all())->toBe(['announce_licence']);

        // A new goal is a new plan; going back resumes the old one.
        $this->james->licences()->update(['licensed_on' => '2022-01-01']);
        $this->putJson('/api/v1/agent/mode', ['goal' => 'grow'])->assertOk();
        $this->getJson('/api/v1/agent/plan')->assertJsonPath('data.goal', 'grow')->assertJsonPath('data.week', 1);
        $this->putJson('/api/v1/agent/mode', ['goal' => 'launch'])->assertOk();
        $this->getJson('/api/v1/agent/plan')->assertJsonPath('data.goal', 'launch')->assertJsonPath('data.week', 3)->assertJsonPath('data.promised', true);
        expect(AgentPlan::query()->count())->toBe(2);
    });

    it('scores the week against targets halved for part-time and names the one to fix', function () {
        $this->putJson('/api/v1/agent/mode/flags', ['flags' => ['part_time']])->assertOk();
        $maria = todayContact($this->james);
        foreach (['2026-11-23 15:00:00', '2026-11-24 15:00:00', '2026-11-17 15:00:00'] as $at) {
            ContactActivity::query()->create(['contact_id' => $maria->id, 'kind' => 'call', 'source' => 'follow-up-boss', 'details' => [], 'occurred_at' => $at]);
        }
        CalendarEvent::query()->create(['user_id' => $this->james->id, 'source' => 'reos', 'kind' => 'appointment', 'title' => 'Buyer consult', 'starts_at' => '2026-11-23 18:00:00', 'ends_at' => '2026-11-23 19:00:00', 'all_day' => false, 'status' => 'confirmed']);
        Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'source' => 'reos', 'property' => '9 Elm', 'side' => 'sell', 'stage' => 'active', 'parties' => [], 'documents' => []]);
        CeCompletion::query()->forceCreate(['user_id' => $this->james->id, 'state_code' => 'TX', 'title' => 'Legal Update I', 'hours' => 1, 'completed_on' => '2026-11-23']);

        $this->getJson('/api/v1/agent/scorecard')->assertOk()->assertJsonPath('data', [
            'week_of' => '2026-11-23',
            'metrics' => [
                ['key' => 'conversations', 'done' => 2, 'target' => 10],
                ['key' => 'appointments', 'done' => 1, 'target' => 1],
                ['key' => 'clients', 'done' => 1, 'target' => 0.5],
                ['key' => 'ce_hours', 'done' => 1, 'target' => 0.5],
            ],
            'fix' => 'conversations',
        ]);
    });

    it('shows Today on the dashboard and the plan page with its scorecard', function () {
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('agentToday.goal', 'launch')->has('agentToday.items', 4)->has('agentToday.day_one', 3));
        $this->get('/agent/plan')->assertInertia(fn (Assert $page) => $page->component('agent/plan')
            ->where('plan.week', 1)->where('scorecard.week_of', '2026-11-23')->has('scorecard.metrics', 4));
        $this->put('/agent/plan/tasks/first_ce_lesson', ['week' => 1, 'done' => true])->assertOk()->assertJsonPath('data.done', 1);

        $candidate = User::factory()->withRole(Role::Candidate)->create();
        $this->actingAs($candidate)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('agentToday', null));
        $this->actingAs($candidate)->getJson('/api/v1/agent/today')->assertNotFound();
    });

    it('keeps another agent\'s to-dos out of Today', function () {
        $linda = User::factory()->withRole(Role::Agent)->create();
        $hers = Todo::query()->create(['user_id' => $linda->id, 'title' => 'Linda only', 'due_date' => '2026-11-24']);

        expect(json_encode($this->getJson('/api/v1/agent/today')->json()))->not->toContain('Linda only');
        $this->post("/today/todos/{$hers->id}/done")->assertNotFound();
    });
});
