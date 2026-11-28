<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\AgentPage;
use App\Models\AgentProfile;
use App\Models\AuditEvent;
use App\Models\Deal;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\AgentFeedbackReceived;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->create(['name' => 'Lone Star Realty']);
    $this->gulf = Organisation::factory()->create(['name' => 'Gulf Coast Homes']);
    $this->priya = User::factory()->withRole(Role::Agent, $this->gulf)->create(['name' => 'Priya Shah', 'email' => 'priya@gulfcoast.test']);
});

/** A closed deal of `$agent` with `$other` on its parties as the other side's agent. */
function closedWith(User $agent, ?User $other, string $stage = 'closed', string $role = "Buyer's agent"): Deal
{
    return Deal::query()->create([
        'uuid' => (string) Str::uuid7(), 'user_id' => $agent->id, 'source' => 'reos', 'property' => '88 Oak Ridge Dr, Katy', 'side' => 'sell', 'stage' => $stage, 'closed_at' => now()->subDays(3),
        'parties' => array_values(array_filter([
            ['name' => 'Ana Lopez', 'role' => 'Seller', 'email' => 'ana@example.test', 'phone' => null],
            $other === null ? null : ['name' => (string) $other->name, 'role' => $role, 'email' => mb_strtoupper((string) $other->email), 'phone' => null],
        ])),
        'documents' => [],
    ]);
}

function rateOn(object $test, User $rater, Deal $deal, User $ratee, int $score, string $comment = 'Clear and on time.'): void
{
    $test->actingAs($rater)->postJson('/api/v1/feedback', ['deal' => $deal->uuid, 'agent' => $ratee->id, 'communication' => $score, 'responsiveness' => $score, 'professionalism' => 5, 'comment' => $comment])->assertOk();
}

describe('feedback from agents (A13.3b)', function () {
    it('shows a rating only after three ratings (the done-when)', function () {
        $raters = array_map(fn (string $n): User => User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => $n]), ['James Miller', 'Tom Reyes', 'Ana Ortiz']);
        $deals = array_map(fn (User $r): Deal => closedWith($r, $this->priya), $raters);

        // The rater sees who they can rate; the client on the deal is never offered.
        $this->actingAs($raters[0])->getJson('/api/v1/feedback')->assertOk()
            ->assertJsonPath('data.to_rate.0.agent.name', 'Priya Shah')->assertJsonCount(1, 'data.to_rate');

        rateOn($this, $raters[0], $deals[0], $this->priya, 3, 'Slow to reply on the inspection.');
        rateOn($this, $raters[1], $deals[1], $this->priya, 4);
        $this->actingAs($this->priya)->getJson('/api/v1/feedback')->assertJsonPath('data.mine', [
            'count' => 2, 'needed' => 3, 'shown' => false, 'average' => null, 'items' => [], 'tip' => null, 'public' => false,
        ]);
        Notification::assertSentTo($this->priya, AgentFeedbackReceived::class, fn (AgentFeedbackReceived $n): bool => ! $n->shown);

        rateOn($this, $raters[2], $deals[2], $this->priya, 2);
        $mine = $this->actingAs($this->priya)->getJson('/api/v1/feedback')->json('data.mine');
        expect($mine)->toMatchArray(['count' => 3, 'shown' => true])
            ->and($mine['average'])->toEqual(['overall' => 3.7, 'communication' => 3.0, 'responsiveness' => 3.0, 'professionalism' => 5.0])
            ->and($mine['items'])->toHaveCount(3)
            ->and($mine['items'][0])->not->toHaveKey('rater')
            ->and($mine['tip'])->toMatchArray(['dimension' => 'communication', 'engine' => 'template']);

        // Rating twice for the same deal isn't allowed, and the deal drops off the list.
        $this->actingAs($raters[0])->postJson('/api/v1/feedback', ['deal' => $deals[0]->uuid, 'agent' => $this->priya->id, 'communication' => 5, 'responsiveness' => 5, 'professionalism' => 5])->assertUnprocessable();
        $this->getJson('/api/v1/feedback')->assertJsonCount(0, 'data.to_rate');
    });

    it('rates only after a verified closing with a REOS agent on the parties', function () {
        $james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
        $open = closedWith($james, $this->priya, 'under_contract');
        $client = User::factory()->create(['name' => 'Ana Lopez', 'email' => 'ana@example.test']);
        $noAgent = closedWith($james, null);

        $this->actingAs($james)->getJson('/api/v1/feedback')->assertJsonCount(0, 'data.to_rate');
        $this->postJson('/api/v1/feedback', ['deal' => $open->uuid, 'agent' => $this->priya->id, 'communication' => 5, 'responsiveness' => 5, 'professionalism' => 5])->assertUnprocessable();
        // A REOS user who isn't an agent (the seller) can't be rated.
        $this->postJson('/api/v1/feedback', ['deal' => $noAgent->uuid, 'agent' => $client->id, 'communication' => 1, 'responsiveness' => 1, 'professionalism' => 1])->assertUnprocessable();
        // Someone else's closed deal can't be used either.
        $tom = User::factory()->withRole(Role::Agent, $this->lone)->create();
        $theirs = closedWith($tom, $this->priya);
        $this->postJson('/api/v1/feedback', ['deal' => $theirs->uuid, 'agent' => $this->priya->id, 'communication' => 1, 'responsiveness' => 1, 'professionalism' => 1])->assertUnprocessable();
    });

    it('lets the agent respond, report with Undo, and choose to show the average publicly', function () {
        foreach (['James Miller', 'Tom Reyes', 'Ana Ortiz', 'Ben Cole'] as $i => $name) {
            $rater = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => $name]);
            rateOn($this, $rater, closedWith($rater, $this->priya), $this->priya, $i === 3 ? 1 : 4, $i === 3 ? 'Rude.' : 'Great to work with.');
        }

        AgentPage::query()->create(['user_id' => $this->priya->id, 'handle' => 'priya-shah', 'published' => true]);
        $this->get('/agent/priya-shah')->assertOk()->assertDontSee('agents they closed with');

        $this->actingAs($this->priya)->from('/feedback')->put('/feedback/visibility', ['public' => true])->assertRedirect('/feedback');
        expect(AgentProfile::query()->where('user_id', $this->priya->id)->value('feedback_public'))->toBeTrue();
        $this->get('/agent/priya-shah')->assertSee('from 4 agents they closed with');

        $rude = collect($this->getJson('/api/v1/feedback')->json('data.mine.items'))->firstWhere('comment', 'Rude.');
        $this->putJson("/api/v1/feedback/{$rude['id']}/response", ['response' => 'Sorry it felt that way. Happy to talk.'])->assertOk()
            ->assertJsonPath('data.mine.items.0.response', 'Sorry it felt that way. Happy to talk.');
        $this->postJson("/api/v1/feedback/{$rude['id']}/report", ['reason' => 'Not true, and not about the deal.'])->assertOk()->assertJsonPath('data.mine.count', 3);
        expect(AuditEvent::query()->where('action', 'agent_feedback.reported')->count())->toBe(1);
        $this->postJson("/api/v1/feedback/{$rude['id']}/report", ['reason' => null])->assertOk()->assertJsonPath('data.mine.count', 4);

        // Only the agent rated can respond or report.
        $this->actingAs(User::factory()->withRole(Role::Agent, $this->lone)->create())->postJson("/api/v1/feedback/{$rude['id']}/report", ['reason' => 'x'])->assertNotFound();

        $this->actingAs($this->priya)->get('/feedback')->assertOk()->assertInertia(fn (Assert $page) => $page->component('feedback/index')->where('mine.shown', true)->has('mine.items', 4));
    });
});
