<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\AuditEvent;
use App\Models\ProviderCategory;
use App\Models\User;
use App\Monitoring\HealthChecks;
use App\Monitoring\RequestMetrics;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
});

describe('platform overview (screen 58)', function () {
    it('is the super admin\'s home, with people online, latency, uptime, traffic, payments and queues', function () {
        $metrics = app(RequestMetrics::class);
        $minute = CarbonImmutable::now()->subMinutes(2);

        foreach ([40, 80, 150, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 900] as $ms) {
            $metrics->record($ms, 200, $minute);
        }

        $metrics->record(120, 500, $minute);
        expect($metrics->flush())->toBe(1);
        $this->artisan('reos:health:check')->assertSuccessful();
        $this->james->forceFill(['last_active_at' => now()->subMinutes(2)])->save();

        $this->actingAs($this->ops)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/overview')
            ->where('overview.p95', 300)
            ->where('overview.p95_target', 300)
            ->where('overview.uptime', fn ($uptime) => is_float($uptime) || is_int($uptime))
            ->where('overview.health.ok', true)
            ->has('overview.traffic', 24)
            ->where('overview.traffic', fn ($traffic) => collect($traffic)->sum('requests') === 21 && collect($traffic)->sum('errors') === 1)
            ->where('overview.online', 1)
            ->has('overview.queues.waiting', 4)
            ->has('overview.cells', 1));

        $this->get('/dashboard?range=30d')->assertInertia(fn (Assert $page) => $page->has('overview.traffic', 30));
    });

    it('shows everyone else their own dashboard', function () {
        $this->actingAs($this->james)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    });

    it('works out p95 from the histogram', function () {
        expect(RequestMetrics::p95(['requests' => 100, 'under_50' => 90, 'under_100' => 5, 'under_200' => 5]))->toBe(100)
            ->and(RequestMetrics::p95(['requests' => 0]))->toBeNull();
    });

    it('publishes a status page anyone can read, with uptime', function () {
        DB::table('health_checks')->insert([
            ['checked_at' => now()->subMinutes(2), 'ok' => true, 'parts' => json_encode(['database' => ['ok' => true, 'ms' => 2]])],
            ['checked_at' => now()->subMinute(), 'ok' => false, 'parts' => json_encode(['database' => ['ok' => false, 'ms' => 2]])],
        ]);

        expect(HealthChecks::uptime(30))->toBe(50.0);
        $this->get('/status')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/status')
            ->where('ok', false)
            ->where('parts', ['database' => false])
            ->where('uptime.30', 50));
    });
});

describe('provider categories (screen 68)', function () {
    it('lists the categories with Texas prices, adds one and audits changes', function () {
        $this->actingAs($this->ops)->get('/admin/provider-categories')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/provider-categories/index')
            ->has('categories', 6)
            ->where('categories.0.status', 'live'));

        $this->post('/admin/provider-categories', [
            'name' => 'Stager', 'licence_check' => 'ID check', 'profile_fields' => "Styles\nRooms per month",
            'status' => 'live', 'prices' => [['state' => 'TX', 'tier' => 'basic', 'monthly' => '19'], ['state' => 'TX', 'tier' => 'pro', 'monthly' => '59']],
        ])->assertSessionHasNoErrors();

        $stager = ProviderCategory::query()->where('key', 'stager')->sole();
        expect($stager->profile_fields)->toBe(['Styles', 'Rooms per month'])
            ->and($stager->prices()->where('tier', 'pro')->value('monthly_cents'))->toBe(5900);

        $this->patch("/admin/provider-categories/{$stager->id}", ['prices' => [['state' => 'TX', 'tier' => 'pro', 'monthly' => '69']]])->assertSessionHasNoErrors();
        $event = AuditEvent::query()->where('action', 'provider_category.updated')->sole();
        expect($event->properties['before']['prices']['TX']['pro'])->toBe(5900)
            ->and($event->properties['after']['prices']['TX']['pro'])->toBe(6900);
    });

    it('refuses tiers and states that do not exist', function () {
        $this->actingAs($this->ops)->post('/admin/provider-categories', ['name' => 'Movers', 'prices' => [['state' => 'ZZ', 'tier' => 'per_lead', 'monthly' => 5]]])
            ->assertSessionHasErrors(['prices.0.state', 'prices.0.tier']);
    });

    it('is for the super admin only', function () {
        $this->actingAs($this->james)->get('/admin/provider-categories')->assertForbidden();
        $this->get('/admin/ai')->assertForbidden();
    });
});

describe('AI and credits (screen 69)', function () {
    it('shows the defaults, saves a new version and audits what changed', function () {
        $this->actingAs($this->ops)->get('/admin/ai')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/ai/index')
            ->where('settings.costs.contract_review', 20)
            ->where('settings.answer_order.candidate.0', 'course_knowledge'));

        $settings = app(AiSettings::class)->current();
        $settings['costs']['voice_todo'] = 3;
        $settings['hourly_limit'] = 50;
        $this->put('/admin/ai', $settings)->assertSessionHasNoErrors();

        expect(app(AiSettings::class)->cost('voice_todo'))->toBe(3)
            ->and(DB::table('ai_settings')->count())->toBe(1)
            ->and(AuditEvent::query()->where('action', 'ai.settings_changed')->sole()->properties)
            ->toMatchArray(['before' => ['costs' => AiSettings::COSTS, 'hourly_limit' => 200], 'after' => ['costs' => [...AiSettings::COSTS, 'voice_todo' => 3], 'hourly_limit' => 50]]);
    });

    it('refuses an answer source that does not exist', function () {
        $settings = app(AiSettings::class)->current();
        $settings['answer_order']['agent'] = ['web_scrape'];

        $this->actingAs($this->ops)->put('/admin/ai', $settings)->assertSessionHasErrors('answer_order.agent.0');
    });

    it('applies the default monthly credits and the hourly abuse limit', function () {
        $settings = app(AiSettings::class)->current();
        $settings['default_monthly_credits'] = 300;
        $settings['hourly_limit'] = 2;
        app(AiSettings::class)->save($settings, $this->ops);
        $credits = app(AiCredits::class);

        expect($credits->left($this->james))->toBe(300);
        $credits->charge($this->james, 'voice', 'ai');
        $credits->charge($this->james, 'voice', 'ai');
        expect($credits->canSpend($this->james))->toBeFalse();
    });
});

describe('activity', function () {
    it('notes when a person was last active, at most once a minute', function () {
        $this->actingAs($this->james)->get('/dashboard')->assertOk();

        expect($this->james->refresh()->last_active_at)->not->toBeNull();
    });
});
