<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Features\Audience;
use App\Features\Flags;
use App\Features\FlagUsage;
use App\Features\FlagWatch;
use App\Features\RolloutStage;
use App\Models\AuditEvent;
use App\Models\FeatureFlag;
use App\Models\Organisation;
use App\Models\Release;
use App\Models\User;
use App\Notifications\FlagRolledBack;
use App\Tenancy\TenantFeature;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
    $this->summit = Organisation::factory()->create(['name' => 'Summit Realty', 'states' => ['TX']]);
    $this->harbor = Organisation::factory()->create(['name' => 'Harbor Homes', 'states' => ['FL']]);
    $this->james = User::factory()->withRole(Role::Agent, $this->summit)->create(['name' => 'James Miller']);
    $this->linda = User::factory()->withRole(Role::Agent, $this->harbor)->create(['name' => 'Linda Park']);
    $this->flags = app(Flags::class);
});

/** Fresh answers, as the next request would see them. */
function flagsNow(): Flags
{
    app()->forgetScopedInstances();

    return app(Flags::class);
}

function rollout(object $test, string $key, array $changes): void
{
    $test->actingAs($test->ops)->put("/admin/features/{$key}/rollout", [
        'stage' => 'off', 'tenant_ids' => [], 'states' => [], 'percent' => 0, 'roles' => [], 'error_threshold' => 5,
        ...$changes,
    ])->assertRedirect()->assertSessionHasNoErrors();
}

describe('rollouts', function () {
    it('done when: a feature turns on for one tenant only, and turning it off rolls it back everywhere', function () {
        expect(flagsNow()->forUser($this->james, 'messages'))->toBeFalse();

        rollout($this, 'messages', ['stage' => 'tenants', 'tenant_ids' => [$this->summit->id]]);

        expect(flagsNow()->forUser($this->james, 'messages'))->toBeTrue()
            ->and(flagsNow()->forUser($this->linda, 'messages'))->toBeFalse()
            ->and(flagsNow()->forTenant($this->summit, 'messages'))->toBeTrue()
            ->and(flagsNow()->forUser($this->ops, 'messages'))->toBeTrue();

        $this->actingAs($this->ops)->post('/admin/features/messages/kill')->assertRedirect();

        expect(flagsNow()->forUser($this->james, 'messages'))->toBeFalse()
            ->and(flagsNow()->forUser($this->ops, 'messages'))->toBeFalse()
            ->and(AuditEvent::query()->where('action', 'flag.killed')->where('subject_id', 'messages')->exists())->toBeTrue();

        // Undo: back on at the rollout it had.
        $this->actingAs($this->ops)->delete('/admin/features/messages/kill')->assertRedirect();
        expect(flagsNow()->forUser($this->james, 'messages'))->toBeTrue()
            ->and(flagsNow()->forUser($this->linda, 'messages'))->toBeFalse();
    });

    it('steps from REOS staff to states, a share of tenants and everyone', function () {
        expect(flagsNow()->forUser($this->ops, 'video_rooms'))->toBeTrue()
            ->and(flagsNow()->forUser($this->james, 'video_rooms'))->toBeFalse();

        rollout($this, 'video_rooms', ['stage' => 'states', 'states' => ['FL']]);
        expect(flagsNow()->forUser($this->linda, 'video_rooms'))->toBeTrue()
            ->and(flagsNow()->forUser($this->james, 'video_rooms'))->toBeFalse();

        // A share of tenants: the same tenants every time, by a stable bucket.
        $inSummit = Flags::bucket('video_rooms', "t{$this->summit->id}");
        rollout($this, 'video_rooms', ['stage' => 'percent', 'percent' => $inSummit + 1]);
        expect(flagsNow()->forUser($this->james, 'video_rooms'))->toBeTrue();
        rollout($this, 'video_rooms', ['stage' => 'percent', 'percent' => $inSummit]);
        expect(flagsNow()->forUser($this->james, 'video_rooms'))->toBeFalse();

        rollout($this, 'video_rooms', ['stage' => 'everyone']);
        expect(flagsNow()->forUser($this->james, 'video_rooms'))->toBeTrue()
            ->and(flagsNow()->forUser($this->linda, 'video_rooms'))->toBeTrue();

        $changed = AuditEvent::query()->where('action', 'flag.rollout_changed')->where('subject_id', 'video_rooms')->latest('id')->first();
        expect($changed?->properties['before']['stage'])->toBe('percent')
            ->and($changed?->properties['after']['stage'])->toBe('everyone');
    });

    it('limits a flag to some roles', function () {
        $broker = User::factory()->withRole(Role::Broker, $this->summit)->create();
        rollout($this, 'notification_centre', ['stage' => 'everyone', 'roles' => ['broker']]);

        expect(flagsNow()->forUser($broker, 'notification_centre'))->toBeTrue()
            ->and(flagsNow()->forUser($this->james, 'notification_centre'))->toBeFalse();
    });

    it('keeps the tenant feature switches as overrides on top of the rollout', function () {
        $school = Organisation::factory()->ofType(OrganisationType::School)->create();

        // Everyone: each type's default, as before.
        expect($this->summit->hasFeature(TenantFeature::VirtualBroker))->toBeTrue()
            ->and($school->hasFeature(TenantFeature::VirtualBroker))->toBeFalse();

        // The tenant's own switch wins over the default…
        $school->update(['features' => ['virtual_broker' => true]]);
        expect($school->fresh()?->hasFeature(TenantFeature::VirtualBroker))->toBeTrue();

        // …but never over the kill switch.
        $this->actingAs($this->ops)->post('/admin/features/virtual_broker/kill');
        expect($school->fresh()?->hasFeature(TenantFeature::VirtualBroker))->toBeFalse()
            ->and($this->summit->fresh()?->hasFeature(TenantFeature::VirtualBroker))->toBeFalse();
    });

    it('turns a beta tenant feature on from its rollout, not from the tenant switch', function () {
        expect($this->summit->hasFeature(TenantFeature::RecruitingAgents))->toBeFalse();

        $this->summit->update(['features' => ['recruiting_agents' => true]]);
        expect($this->summit->fresh()?->hasFeature(TenantFeature::RecruitingAgents))->toBeFalse();

        rollout($this, 'recruiting_agents', ['stage' => 'tenants', 'tenant_ids' => [$this->summit->id]]);
        expect($this->summit->fresh()?->hasFeature(TenantFeature::RecruitingAgents))->toBeTrue()
            ->and($this->harbor->fresh()?->hasFeature(TenantFeature::RecruitingAgents))->toBeFalse();
    });

    it('resolves an audience without the database', function () {
        $flag = new FeatureFlag(['key' => 'messages', 'stage' => RolloutStage::Internal, 'percent' => 0, 'tenant_feature' => false]);
        $staff = new Audience(null, null, [], [], Role::SuperAdmin, 1, true);
        $agent = Audience::person($this->james, Role::Agent, $this->summit);

        expect($this->flags->resolve($flag, $staff))->toBeTrue()
            ->and($this->flags->resolve($flag, $agent))->toBeFalse()
            ->and($agent->featureScopeSerialize())->toBe("t:{$this->summit->id}|agent");
    });
});

describe('automatic rollback', function () {
    it('steps a flag back one stage when errors stay high for 10 minutes, and tells the super admins', function () {
        Notification::fake();
        rollout($this, 'messages', ['stage' => 'everyone', 'error_threshold' => 5]);
        FeatureFlag::query()->where('key', 'messages')->update(['stage_changed_at' => now()->subHour()]);

        $start = CarbonImmutable::now()->startOfMinute();

        foreach (range(1, 10) as $back) {
            $this->travelTo($start->subMinutes($back)->addSeconds(5));
            for ($i = 0; $i < 20; $i++) {
                $request = new FlagUsage(app('cache.store'));
                $request->used('messages');

                if ($i < 2) {
                    $request->failed();
                }
            }
        }

        $this->travelTo($start->addSeconds(10));
        $this->artisan('reos:flags:watch')->assertSuccessful();

        $flag = FeatureFlag::query()->where('key', 'messages')->firstOrFail();
        expect($flag->stage)->toBe(RolloutStage::Percent)
            ->and($flag->rolled_back_from)->toBe(RolloutStage::Everyone)
            ->and($flag->rolled_back_at)->not->toBeNull()
            ->and(AuditEvent::query()->where('action', 'flag.rolled_back')->value('properties'))->toMatchArray(['from' => 'everyone', 'to' => 'percent', 'error_rate' => 10]);

        Notification::assertSentTo($this->ops, FlagRolledBack::class);

        // Judged again only once 10 minutes have passed at the new stage.
        $this->artisan('reos:flags:watch')->assertSuccessful();
        expect($flag->fresh()?->stage)->toBe(RolloutStage::Percent);
    });

    it('leaves a flag alone when errors are below the threshold or too few uses', function () {
        rollout($this, 'messages', ['stage' => 'everyone', 'error_threshold' => 20]);
        FeatureFlag::query()->where('key', 'messages')->update(['stage_changed_at' => now()->subHour()]);
        $start = CarbonImmutable::now()->startOfMinute();

        foreach (range(1, 10) as $back) {
            $this->travelTo($start->subMinutes($back)->addSeconds(5));

            for ($i = 0; $i < 20; $i++) {
                $request = new FlagUsage(app('cache.store'));
                $request->used('messages');

                if ($i < 2) {
                    $request->failed();
                }
            }
        }

        $this->travelTo($start->addSeconds(10));
        expect(app(FlagWatch::class)->run())->toBe([])
            ->and(FeatureFlag::query()->where('key', 'messages')->value('stage'))->toBe(RolloutStage::Everyone);
    });

    it('counts an error against the flags that were on in that request', function () {
        $usage = new FlagUsage(app('cache.store'));
        $usage->used('messages');
        $usage->used('messages');
        $usage->failed();
        $usage->used('video_rooms');
        $minute = CarbonImmutable::now()->startOfMinute();

        expect($usage->read('messages', $minute, 'uses'))->toBe(1)
            ->and($usage->read('messages', $minute, 'errors'))->toBe(1)
            ->and($usage->read('video_rooms', $minute, 'errors'))->toBe(1);
    });

    it('tags reported exceptions with the flags on in the request', function () {
        app(FlagUsage::class)->used('messages');
        report(new RuntimeException('Boom'));

        expect(app(FlagUsage::class)->read('messages', CarbonImmutable::now()->startOfMinute(), 'errors'))->toBe(1);
    });
});

describe('features and releases (screen 70)', function () {
    it('shows every flag with its rollout, who sees it and errors since rollout', function () {
        rollout($this, 'messages', ['stage' => 'tenants', 'tenant_ids' => [$this->summit->id], 'roles' => ['agent', 'broker']]);

        $this->actingAs($this->ops)->get('/admin/features')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/features/index')
            ->has('flags', 9)
            ->where('flags.6.key', 'messages')
            ->where('flags.6.stage', 'tenants')
            ->where('flags.6.tenants.0.name', 'Summit Realty')
            ->where('flags.6.roles', ['agent', 'broker'])
            ->where('flags.0.tenant_feature', true)
            ->has('options.tenants', 2)
            ->where('options.window_minutes', 10));
    });

    it('is for super admins only', function () {
        $this->actingAs($this->james)->get('/admin/features')->assertForbidden();
        $this->actingAs($this->james)->post('/admin/features/messages/kill')->assertForbidden();
    });

    it('checks a rollout change', function () {
        $this->actingAs($this->ops)->put('/admin/features/messages/rollout', [
            'stage' => 'sideways', 'tenant_ids' => [Organisation::platform()->id], 'states' => ['ZZ'], 'percent' => 140, 'roles' => ['super_admin'], 'error_threshold' => 0,
        ])->assertSessionHasErrors(['stage', 'tenant_ids.0', 'states.0', 'percent', 'roles.0', 'error_threshold']);

        $this->actingAs($this->ops)->put('/admin/features/not_a_flag/rollout', [])->assertNotFound();
    });

    it('publishes a release, rolls its flags out to everyone and shows its note once per role', function () {
        $this->actingAs($this->ops)->post('/admin/releases', [
            'version' => '6.2.0',
            'released_on' => now()->toDateString(),
            'notes' => ['agent' => 'Messages are here: talk to your broker and team in REOS.', 'broker' => ''],
            'flags' => ['messages'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $release = Release::query()->where('version', '6.2.0')->firstOrFail();
        expect($release->notes)->toBe(['agent' => 'Messages are here: talk to your broker and team in REOS.'])
            ->and(FeatureFlag::query()->where('key', 'messages')->value('stage'))->toBe(RolloutStage::Everyone)
            ->and(AuditEvent::query()->where('action', 'release.published')->exists())->toBeTrue()
            ->and(flagsNow()->forUser($this->linda, 'messages'))->toBeTrue();

        // What's new on the web, for agents only, until dismissed.
        $this->actingAs($this->james)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('whatsNew.version', '6.2.0')
            ->where('whatsNew.note', 'Messages are here: talk to your broker and team in REOS.'));
        $broker = User::factory()->withRole(Role::Broker, $this->summit)->create();
        $this->actingAs($broker)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('whatsNew', null));

        $this->actingAs($this->james)->post("/whats-new/{$release->id}/dismiss")->assertRedirect();
        $this->actingAs($this->james)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('whatsNew', null));

        $this->actingAs($this->ops)->get('/admin/features')->assertInertia(fn (Assert $page) => $page
            ->where('releases.0.version', '6.2.0')
            ->where('releases.0.dismissed', 1)
            ->where('releases.0.flags', ['messages']));
    });

    it('needs a note for at least one role and a new version', function () {
        $this->actingAs($this->ops)->post('/admin/releases', [
            'version' => '6.2.0', 'released_on' => now()->toDateString(), 'notes' => ['agent' => '  '], 'flags' => ['nope'],
        ])->assertSessionHasErrors(['notes', 'flags.0']);
    });
});

describe('what\'s new on the phone', function () {
    it('gives the latest note for the person\'s role and dismisses it', function () {
        $release = Release::query()->create([
            'version' => '6.2.1', 'released_on' => now()->toDateString(), 'published_at' => now(),
            'notes' => ['agent' => 'Announcements from your broker now show on Today.'],
        ]);
        Release::query()->create([
            'version' => '6.1.0', 'released_on' => now()->subDays(40)->toDateString(), 'published_at' => now()->subDays(40),
            'notes' => ['agent' => 'Too old to show.'],
        ]);

        $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/me/whats-new')->assertOk()
            ->assertJsonPath('release.version', '6.2.1')
            ->assertJsonPath('release.note', 'Announcements from your broker now show on Today.');

        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/me/whats-new/{$release->id}/dismiss")->assertNoContent();
        $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/me/whats-new')->assertOk()->assertJsonPath('release', null);
        $this->actingAs($this->linda, 'sanctum')->getJson('/api/v1/me/whats-new')->assertJsonPath('release.version', '6.2.1');
    });
});
