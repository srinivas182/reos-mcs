<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Buyer;
use App\Billing\Ledger\Ledger;
use App\Billing\Ledger\Points;
use App\Billing\PriceInterval;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use App\Models\AgentProfile;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\DailyGoalDay;
use App\Models\Entitlement;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\Product;
use App\Models\SocialPost;
use App\Models\Todo;
use App\Models\User;
use App\Rewards\PointRates;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Friday 27 November 2026, 10:00 in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller', 'current_state' => 'TX']);
    AgentProfile::query()->create(['user_id' => $this->james->id, 'goal' => 'launch']);
});

/** The agent's own activity on a day: notes with contacts, follow-ups done and a published post. */
function didWork(User $agent, CarbonImmutable $at, int $conversations, int $followUps, int $posts): void
{
    $contact = Contact::query()->create(['uuid' => (string) Str::uuid(), 'owner_type' => 'user', 'owner_id' => $agent->id, 'first_name' => 'Ana', 'last_name' => 'Client']);
    foreach (range(1, max(1, $conversations)) as $i) {
        if ($conversations > 0) {
            (new ContactNote)->forceFill(['contact_id' => $contact->id, 'author_id' => $agent->id, 'body' => "Call {$i}", 'source' => 'reos', 'created_at' => $at, 'updated_at' => $at])->save();
        }
    }
    foreach ($followUps > 0 ? range(1, $followUps) : [] as $i) {
        (new Todo)->forceFill(['user_id' => $agent->id, 'title' => "Follow up {$i}", 'source' => 'typed', 'done_at' => $at])->save();
    }
    foreach ($posts > 0 ? range(1, $posts) : [] as $i) {
        (new SocialPost)->forceFill(['uuid' => (string) Str::uuid(), 'user_id' => $agent->id, 'body' => 'Open house Saturday', 'media' => [], 'attribution' => 'James · Lone Star Realty', 'status' => 'published', 'published_at' => $at])->save();
    }
}

describe('daily goals and points (A13.3a)', function () {
    it('awards each met goal once, keeps a streak, and settles days the app was not opened', function () {
        // Wednesday and Thursday: every launch goal but the CE lesson. Launch has a CE lesson goal, so turn it off for the brokerage.
        $this->actingAs($this->linda)->put('/organisation/rewards/goals/launch', ['targets' => ['conversations' => 5, 'ce_lesson' => 0, 'follow_ups' => 3, 'post' => 1]])->assertSessionHasNoErrors();
        expect(AuditEvent::query()->where('action', 'daily_goals.updated')->count())->toBe(1);

        didWork($this->james, now()->subDays(2), 5, 3, 1);
        didWork($this->james, now()->subDay(), 6, 3, 1);
        // The nightly run settles yesterday only; Wednesday is settled when the agent looks back… so run both nights.
        $this->travelTo(now()->subDay()->setTime(6, 40));
        $this->artisan('reos:daily-goals')->assertSuccessful();
        $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00', 'America/Chicago'));
        $this->artisan('reos:daily-goals')->assertSuccessful();
        expect(DailyGoalDay::query()->where('user_id', $this->james->id)->where('completed', true)->pluck('day')->all())->toEqualCanonicalizing(['2026-11-25', '2026-11-26']);

        // Today: conversations so far, not the rest.
        didWork($this->james, now(), 5, 0, 0);
        $today = $this->actingAs($this->james)->getJson('/api/v1/goals/today')->assertOk()->json('data');
        $byKey = collect($today['goals'])->keyBy('key');
        expect(array_keys($byKey->all()))->toBe(['conversations', 'follow_ups', 'post'])
            ->and($byKey['conversations'])->toMatchArray(['done' => 5, 'target' => 5, 'met' => true, 'points' => 20])
            ->and($byKey['post']['met'])->toBeFalse()
            ->and($today)->toMatchArray(['points_today' => 20, 'streak' => 2, 'best_streak' => 2, 'all_done' => false]);

        // Two days of 20 + 15 + 15 + 25 (all done) = 75, plus 20 today. Asking again awards nothing twice.
        $this->getJson('/api/v1/goals/today')->assertJsonPath('data.balance', 170);
        expect(app(Points::class)->balance($this->james))->toBe(170);

        // Finishing today extends the streak.
        didWork($this->james, now()->addMinute(), 0, 3, 1);
        $this->travel(2)->minutes();
        $this->getJson('/api/v1/goals/today')->assertJsonPath('data.streak', 3)->assertJsonPath('data.all_done', true)->assertJsonPath('data.points_today', 75);
    });

    it('pays brokerage bonus points from the brokerage, up to the campaign budget', function () {
        $this->actingAs($this->linda)->post('/organisation/rewards/campaigns', ['title' => 'Double conversations', 'goal' => 'conversations', 'multiplier' => 2, 'starts_on' => '2026-11-27', 'ends_on' => '2026-11-30', 'budget' => 100])->assertSessionHasNoErrors();
        $this->post('/organisation/rewards/campaigns', ['title' => 'Too long', 'goal' => 'all', 'multiplier' => 2, 'starts_on' => '2026-11-27', 'ends_on' => '2027-06-30', 'budget' => 1000])->assertSessionHasErrors('ends_on');
        expect(AuditEvent::query()->where('action', 'points_campaign.created')->count())->toBe(1);

        didWork($this->james, now(), 5, 0, 0);
        $today = $this->actingAs($this->james)->getJson('/api/v1/goals/today')->assertOk()->json('data');
        expect($today)->toMatchArray(['points_today' => 20, 'bonus_today' => 20])
            ->and($today['campaigns'][0])->toMatchArray(['title' => 'Double conversations', 'multiplier' => 2])
            ->and(app(Points::class)->balance($this->james))->toBe(40);

        $ledger = app(Ledger::class);
        expect($ledger->balance($ledger->organisation($this->lone, 'points')))->toBe(-20);

        // Another agent at a different brokerage gets no bonus.
        $gulf = Organisation::factory()->create(['name' => 'Gulf Coast Homes']);
        $zed = User::factory()->withRole(Role::Agent, $gulf)->create(['name' => 'Zed Moss']);
        AgentProfile::query()->create(['user_id' => $zed->id, 'goal' => 'launch']);
        didWork($zed, now(), 5, 0, 0);
        $this->actingAs($zed)->getJson('/api/v1/goals/today')->assertJsonPath('data.bonus_today', 0)->assertJsonPath('data.campaigns', []);

        $this->actingAs($this->linda)->get('/organisation/rewards')->assertOk()->assertInertia(fn (Assert $page) => $page->component('organisation/rewards')
            ->where('campaigns.0.spent', 20)->where('campaigns.0.active', true)->where('modes.launch.own', false));
    });

    it('lets points pay for AI credits and a month of membership, never for cash', function () {
        $pack = Product::query()->create(['key' => 'ai-credits-1000', 'name' => '1,000 AI credits', 'kind' => ProductKind::CreditPack, 'buyer' => Buyer::Agent, 'includes' => [], 'features' => [], 'status' => ProductStatus::Live, 'credits' => 1000]);
        $packPrice = Price::query()->create(['product_id' => $pack->id, 'state_code' => 'TX', 'amount_cents' => 1500, 'interval' => PriceInterval::Once, 'currency' => 'usd', 'starts_at' => now()->subMonth()]);
        $plan = Product::query()->create(['key' => 'agent-core', 'name' => 'Agent Core', 'kind' => ProductKind::Plan, 'buyer' => Buyer::Agent, 'includes' => [], 'features' => ['agent.core'], 'status' => ProductStatus::Live]);
        $planPrice = Price::query()->create(['product_id' => $plan->id, 'state_code' => 'TX', 'amount_cents' => 2900, 'interval' => PriceInterval::Month, 'currency' => 'usd', 'starts_at' => now()->subMonth()]);
        app(Points::class)->award($this->james, 3000, 'test:grant');

        $summary = $this->actingAs($this->james)->getJson('/api/v1/rewards')->assertOk()->json('data');
        expect($summary)->toMatchArray(['balance' => 3000, 'value_cents' => 3000])
            ->and(collect($summary['options'])->pluck('points', 'kind')->all())->toBe(['credit_pack' => 1500, 'plan' => 2900]);

        $this->postJson('/api/v1/rewards/redeem', ['price' => $packPrice->id])->assertOk()->assertJsonPath('data.balance', 1500);
        $ledger = app(Ledger::class);
        expect($ledger->balance($ledger->person($this->james, 'topup')))->toBe(1000);
        // Not enough left for a month of membership.
        $this->postJson('/api/v1/rewards/redeem', ['price' => $planPrice->id])->assertUnprocessable();

        app(Points::class)->award($this->james, 2000, 'test:more');
        $this->from('/rewards')->post('/rewards/redeem', ['price' => $planPrice->id])->assertRedirect('/rewards');
        $access = Entitlement::query()->where('user_id', $this->james->id)->where('feature', 'agent.core')->sole();
        expect($access->ends_at?->toDateString())->toBe(now()->addMonth()->toDateString())
            ->and(AuditEvent::query()->where('action', 'points.redeemed')->count())->toBe(2);

        $this->get('/rewards')->assertOk()->assertInertia(fn (Assert $page) => $page->component('rewards/index')->where('rewards.balance', 600)->has('rewards.history', 4)->has('today.goals', 4));
        // Something not on the rewards list can't be bought with points.
        $this->postJson('/api/v1/rewards/redeem', ['price' => 999999])->assertUnprocessable();
    });

    it('uses the super admin\'s rates', function () {
        $ops = User::factory()->superAdmin()->withTwoFactor()->create();
        $rates = app(PointRates::class)->current();
        $rates['points']['conversations'] = 50;
        $this->actingAs($ops)->put('/admin/points', $rates)->assertSessionHasNoErrors();
        expect(app(PointRates::class)->current()['points']['conversations'])->toBe(50);
        $this->get('/admin/points')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/points/index')->where('rates.points.conversations', 50));

        didWork($this->james, now(), 5, 0, 0);
        $this->actingAs($this->james)->getJson('/api/v1/goals/today')->assertJsonPath('data.points_today', 50);
        // Agents can't change rates or brokerage goals.
        $this->put('/admin/points', $rates)->assertForbidden();
        $this->put('/organisation/rewards/goals/launch', ['targets' => ['conversations' => 1, 'ce_lesson' => 0, 'follow_ups' => 0, 'post' => 0]])->assertForbidden();
    });
});
