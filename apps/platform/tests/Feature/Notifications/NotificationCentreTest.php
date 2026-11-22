<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Features\Flags;
use App\Features\RolloutStage;
use App\Models\NotificationSetting;
use App\Models\State;
use App\Models\User;
use App\Notifications\Digest;
use App\Notifications\FlagRolledBack;
use App\Notifications\StateWentLive;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    $this->state = State::query()->firstOrCreate(['code' => 'LA'], ['name' => 'Louisiana', 'status' => 'live']);
});

/** What the deliveries log says for a kind and channel. */
function delivered(string $kind, string $channel): array
{
    return DB::table('notification_deliveries')->where('kind', $kind)->where('channel', $channel)->orderBy('id')->pluck('status')->all();
}

describe('quiet hours', function () {
    it('done when: quiet hours hold back non-urgent alerts and deliver them in the morning', function () {
        // 10:30 PM in Texas: inside the default 9 PM to 7 AM.
        $this->travelTo(CarbonImmutable::parse('2026-11-24 22:30', 'America/Chicago'));
        $this->james->notify(new StateWentLive($this->state));

        // The inbox gets it at once (silent); the email waits.
        expect($this->james->notifications()->count())->toBe(1)
            ->and(delivered('state_went_live', 'inbox'))->toBe(['sent'])
            ->and(delivered('state_went_live', 'email'))->toBe([])
            ->and(delivered('state_went_live', 'alerts'))->toBe(['held']);

        $held = DB::table('held_notifications')->first();
        expect(CarbonImmutable::parse($held->release_at)->setTimezone('America/Chicago')->format('Y-m-d H:i'))->toBe('2026-11-25 07:00');

        // Still night: nothing goes.
        $this->travelTo(CarbonImmutable::parse('2026-11-25 06:55', 'America/Chicago'));
        $this->artisan('reos:notifications:release')->assertSuccessful();
        expect(delivered('state_went_live', 'email'))->toBe([]);

        // Morning: the email goes out, once.
        $this->travelTo(CarbonImmutable::parse('2026-11-25 07:05', 'America/Chicago'));
        $this->artisan('reos:notifications:release')->assertSuccessful();
        $this->artisan('reos:notifications:release')->assertSuccessful();
        expect(delivered('state_went_live', 'email'))->toBe(['released'])
            ->and($this->james->notifications()->count())->toBe(1);
    });

    it('lets urgent alerts through at night', function () {
        $ops = User::factory()->superAdmin()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 23:30', 'America/Chicago'));
        $flag = app(Flags::class)->flag('messages');

        $ops->notify(new FlagRolledBack($flag, RolloutStage::Everyone, 12.5));

        expect(delivered('flag_rolled_back', 'email'))->toBe(['sent'])
            ->and(DB::table('held_notifications')->count())->toBe(0);
    });

    it('follows the person\'s own hours and time zone, or none', function () {
        NotificationSetting::query()->create(['user_id' => $this->james->id, 'quiet_start' => '13:00', 'quiet_end' => '15:00', 'timezone' => 'America/New_York']);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 13:30', 'America/New_York'));
        $this->james->notify(new StateWentLive($this->state));
        expect(delivered('state_went_live', 'alerts'))->toBe(['held']);

        NotificationSetting::query()->whereKey($this->james->id)->update(['quiet_enabled' => false]);
        $this->james->fresh()?->notify(new StateWentLive($this->state));
        expect(delivered('state_went_live', 'email'))->toBe(['sent']);
    });
});

describe('preferences', function () {
    it('turns a channel off per kind, but never security messages', function () {
        $this->actingAs($this->james)->put('/settings/notifications', ['channels' => ['state_went_live' => ['email' => false]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->james)->put('/settings/notifications', ['channels' => ['new_device' => ['email' => false]]])->assertSessionHasErrors('channels');

        $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00', 'America/Chicago'));
        $this->james->fresh()?->notify(new StateWentLive($this->state));
        expect(delivered('state_went_live', 'inbox'))->toBe(['sent'])->and(delivered('state_went_live', 'email'))->toBe([]);

        $this->actingAs($this->james)->get('/settings/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/notifications')
            ->where('quiet', ['enabled' => true, 'start' => '21:00', 'end' => '07:00'])
            ->where('kinds', fn ($kinds) => collect($kinds)->firstWhere('kind', 'state_went_live')['channels'] === ['inbox' => true, 'push' => true, 'email' => false]
                && collect($kinds)->firstWhere('kind', 'new_device')['locked'] === true));
    });

    it('saves quiet hours and the time zone from the phone', function () {
        $this->actingAs($this->james, 'sanctum')->putJson('/api/v1/me/notification-settings', ['quiet' => ['enabled' => true, 'start' => '22:00', 'end' => '06:30'], 'timezone' => 'America/Denver'])
            ->assertOk()
            ->assertJsonPath('quiet.start', '22:00')
            ->assertJsonPath('timezone', 'America/Denver');

        $this->actingAs($this->james, 'sanctum')->putJson('/api/v1/me/notification-settings', ['quiet' => ['enabled' => true, 'start' => '22:00', 'end' => '22:00']])->assertUnprocessable();
        $this->actingAs($this->james, 'sanctum')->putJson('/api/v1/me/notification-settings', ['timezone' => 'Mars/Olympus'])->assertUnprocessable();
    });
});

describe('digests', function () {
    it('gathers a kind into a daily digest sent at 8 AM local time', function () {
        $this->actingAs($this->james)->put('/settings/notifications', ['digests' => ['state_went_live' => 'daily']])->assertSessionHasNoErrors();
        $this->actingAs($this->james)->put('/settings/notifications', ['digests' => ['payment_failed' => 'daily']])->assertSessionHasErrors('digests');

        $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00', 'America/Chicago'));
        $this->james->fresh()?->notify(new StateWentLive($this->state));
        expect(delivered('state_went_live', 'alerts'))->toBe(['digested'])->and(DB::table('digest_items')->count())->toBe(1);

        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 07:10', 'America/Chicago'));
        $this->artisan('reos:notifications:digests')->assertSuccessful();
        Notification::assertNothingSentTo($this->james);

        $this->travelTo(CarbonImmutable::parse('2026-11-25 08:02', 'America/Chicago'));
        $this->artisan('reos:notifications:digests')->assertSuccessful();
        $this->artisan('reos:notifications:digests')->assertSuccessful();
        Notification::assertSentToTimes($this->james, Digest::class, 1);
        Notification::assertSentTo($this->james, Digest::class, fn (Digest $d) => $d->frequency === 'daily' && $d->items[0]['title'] === 'REOS is now live in Louisiana');
    });
});

describe('the inbox', function () {
    it('lists, counts and marks read on the web and the phone', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00', 'America/Chicago'));
        $this->james->notify(new StateWentLive($this->state));
        $this->james->notify(new StateWentLive($this->state));

        $this->actingAs($this->james)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 2));
        $id = $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('unread', 2)
            ->assertJsonPath('items.0.kind', 'state_went_live')
            ->assertJsonPath('items.0.url', url('/get-started'))
            ->json('items.0.id');

        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/notifications/{$id}/read")->assertJsonPath('unread', 1);
        $this->actingAs($this->james)->post('/notifications/read')->assertRedirect();
        $this->actingAs($this->james)->get('/notifications')->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('unread', 0)
            ->has('items', 2));
    });
});
