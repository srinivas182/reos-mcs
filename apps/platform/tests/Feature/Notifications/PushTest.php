<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Features\Flags;
use App\Integrations\IntegrationMode;
use App\Messaging\Conversations;
use App\Messaging\Safety;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\PushDevice;
use App\Models\State;
use App\Models\User;
use App\Notifications\Centre\Push\SimulatorPushSender;
use App\Notifications\StateWentLive;
use App\Services\Identity\AccountDeletion;
use App\Services\Privacy\DataExport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $flags = app(Flags::class);
    $flag = $flags->flag('messages');
    $flag->forceFill(['stage' => 'everyone'])->save();
    $flags->changed($flag);

    $this->lone = Organisation::factory()->create(['name' => 'Lone Star Realty']);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->create(['name' => 'Linda Park']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
    $this->phone = $this->james->createToken("James's iPhone")->plainTextToken;
    $this->token = 'ExponentPushToken[jamesIphone0001]';
    // Midday in Texas: outside quiet hours.
    $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00', 'America/Chicago'));
});

/** @return list<array<string, mixed>> */
function outbox(): array
{
    return app(SimulatorPushSender::class)->outbox();
}

function turnOnPush(object $test): void
{
    $test->withToken($test->phone)->postJson('/api/v1/me/push-devices', ['token' => $test->token, 'platform' => 'ios', 'name' => "James's iPhone"])
        ->assertCreated()
        ->assertJsonPath('phones', 1);
    // Each request signs in afresh with the phone's token.
    app('auth')->forgetGuards();
}

describe('phones', function () {
    it('registers a phone once, and signing out removes it', function () {
        turnOnPush($this);
        turnOnPush($this);

        expect(PushDevice::query()->count())->toBe(1)
            ->and(PushDevice::query()->sole()->personal_access_token_id)->not->toBeNull();

        $this->withToken($this->phone)->getJson('/api/v1/me/notification-settings')->assertJsonPath('phones', 1)->assertJsonPath('kinds.0.channels.push', true);
        app('auth')->forgetGuards();

        $this->withToken($this->phone)->postJson('/api/v1/auth/sign-out')->assertNoContent();
        expect(PushDevice::query()->count())->toBe(0);
    });

    it('turns off on this phone only, and refuses tokens that are not Expo tokens', function () {
        turnOnPush($this);
        PushDevice::query()->create(['user_id' => $this->james->id, 'token' => 'ExponentPushToken[jamesIpad00002]', 'platform' => 'ios']);

        $this->withToken($this->phone)->deleteJson('/api/v1/me/push-devices/current')->assertNoContent();
        expect(PushDevice::query()->pluck('token')->all())->toBe(['ExponentPushToken[jamesIpad00002]']);
        app('auth')->forgetGuards();

        $this->withToken($this->phone)->postJson('/api/v1/me/push-devices', ['token' => 'not-a-token', 'platform' => 'ios'])->assertUnprocessable();
    });

    it('moves a phone to whoever signs in on it', function () {
        turnOnPush($this);
        $linda = $this->linda->createToken('Shared iPad')->plainTextToken;

        $this->withToken($linda)->postJson('/api/v1/me/push-devices', ['token' => $this->token, 'platform' => 'ios'])->assertCreated();

        expect(PushDevice::query()->sole()->user_id)->toBe($this->linda->id);
    });
});

describe('sending', function () {
    it('done when: a message reaches the phone as a push, one per conversation', function () {
        turnOnPush($this);
        $conversations = app(Conversations::class);

        $conversation = $conversations->start($this->linda, [$this->james->id], 'Can you look at the Elm St offer?');
        $conversations->post($this->linda, $conversation, 'I need it by 5.');

        $pushes = outbox();
        expect($pushes)->toHaveCount(2)
            ->and($pushes[0]['to'])->toBe($this->token)
            ->and($pushes[0]['title'])->toBe('Linda Park · 2 new messages')
            ->and($pushes[0]['body'])->toBe('I need it by 5.')
            ->and($pushes[0]['collapseId'])->toBe('conversation-'.$conversation->uuid)
            ->and($pushes[0]['channelId'])->toBe('messages')
            ->and($pushes[0]['data']['path'])->toBe('/messages/'.$conversation->uuid)
            ->and($pushes[1]['title'])->toBe('Linda Park');

        // Chats stay out of the inbox by default; the Messages badge counts them.
        expect($this->james->notifications()->count())->toBe(0)
            ->and(DB::table('push_tickets')->count())->toBe(2)
            ->and(DB::table('notification_deliveries')->where('channel', 'push')->pluck('status')->all())->toBe(['sent', 'sent']);
    });

    it('holds chats in quiet hours and skips the ones read by morning', function () {
        turnOnPush($this);
        $conversations = app(Conversations::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 22:30', 'America/Chicago'));

        $first = $conversations->start($this->linda, [$this->james->id], 'Offer is in.');
        $dana = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Dana Brooks']);
        $second = $conversations->start($this->linda, [$this->james->id, $dana->id], 'Huddle moved to 9.', 'Friday huddle');
        expect(outbox())->toBe([])->and(DB::table('held_notifications')->where('user_id', $this->james->id)->count())->toBe(2);

        // James reads the first one on the web before bed.
        $conversations->read($this->james, $first);

        $this->travelTo(CarbonImmutable::parse('2026-11-25 07:05', 'America/Chicago'));
        $this->artisan('reos:notifications:release')->assertSuccessful();

        expect(outbox())->toHaveCount(1)
            ->and(outbox()[0]['data']['path'])->toBe('/messages/'.$second->uuid);
    });

    it('sends other kinds with the badge, and nothing to people who blocked the sender', function () {
        turnOnPush($this);
        $state = State::query()->firstOrCreate(['code' => 'LA'], ['name' => 'Louisiana', 'status' => 'live']);

        $this->james->notify(new StateWentLive($state));
        expect(outbox()[0])->toMatchArray(['title' => 'REOS is now live in Louisiana', 'badge' => 1, 'channelId' => 'alerts', 'priority' => 'default'])
            ->and(outbox()[0]['data']['path'])->toBe('/notifications');

        // In a group, someone who blocked the sender gets no push from them.
        $dana = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Dana Brooks']);
        PushDevice::query()->create(['user_id' => $dana->id, 'token' => 'ExponentPushToken[danaAndroid001]', 'platform' => 'android']);
        $group = app(Conversations::class)->start($this->linda, [$this->james->id, $dana->id], 'Huddle at 9.', 'Friday huddle');
        app(Safety::class)->block($this->james, $this->linda);
        app(Conversations::class)->post($this->linda, $group, 'Bring the offer.');

        expect(array_column(outbox(), 'to'))->toBe(['ExponentPushToken[danaAndroid001]', 'ExponentPushToken[danaAndroid001]', $this->token, $this->token])
            ->and(outbox()[0]['title'])->toBe('Linda Park in Friday huddle · 2 new messages');
    });

    it('turns push off per kind like any other channel', function () {
        turnOnPush($this);
        $this->actingAs($this->james)->put('/settings/notifications', ['channels' => ['message' => ['push' => false]]])->assertSessionHasNoErrors();

        app(Conversations::class)->start($this->linda, [$this->james->id], 'Offer is in.');

        expect(outbox())->toBe([]);
    });
});

describe('dead phones', function () {
    it('removes a phone Expo says is gone, at once or from the receipts', function () {
        IntegrationSetting::query()->create(['integration_id' => 'expo-push', 'mode' => IntegrationMode::Sandbox]);
        $gone = PushDevice::query()->create(['user_id' => $this->james->id, 'token' => 'ExponentPushToken[deletedApp0001]', 'platform' => 'android']);
        $later = PushDevice::query()->create(['user_id' => $this->james->id, 'token' => 'ExponentPushToken[turnedOff00002]', 'platform' => 'ios']);
        $fine = PushDevice::query()->create(['user_id' => $this->james->id, 'token' => 'ExponentPushToken[stillHere00003]', 'platform' => 'ios']);

        Http::fake([
            'exp.host/--/api/v2/push/send' => Http::response(['data' => [
                ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered']],
                ['status' => 'ok', 'id' => 'ticket-later'],
                ['status' => 'ok', 'id' => 'ticket-fine'],
            ]]),
            'exp.host/--/api/v2/push/getReceipts' => Http::response(['data' => [
                'ticket-later' => ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
                'ticket-fine' => ['status' => 'ok'],
            ]]),
        ]);

        $state = State::query()->firstOrCreate(['code' => 'LA'], ['name' => 'Louisiana', 'status' => 'live']);
        $this->james->notify(new StateWentLive($state));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://exp.host/--/api/v2/push/send' && count($r->data()) === 3 && $r->data()[2]['to'] === $fine->token);
        expect(PushDevice::query()->find($gone->id))->toBeNull();

        // Receipts are checked 15 minutes later.
        $this->artisan('reos:push:receipts')->assertSuccessful();
        expect(DB::table('push_tickets')->count())->toBe(2);

        $this->travel(16)->minutes();
        $this->artisan('reos:push:receipts')->assertSuccessful();

        expect(PushDevice::query()->pluck('id')->all())->toBe([$fine->id])
            ->and(DB::table('push_tickets')->count())->toBe(0);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://exp.host/--/api/v2/push/getReceipts' && $r['ids'] === ['ticket-later', 'ticket-fine']);
    });
});

describe('privacy', function () {
    it('exports the inbox, choices and phones (never the token), and prunes old logs', function () {
        turnOnPush($this);
        $state = State::query()->firstOrCreate(['code' => 'LA'], ['name' => 'Louisiana', 'status' => 'live']);
        $this->james->notify(new StateWentLive($state));

        $export = app(DataExport::class)->for($this->james);
        expect($export['notifications']['inbox'][0]['notification']['title'])->toBe('REOS is now live in Louisiana')
            ->and($export['notifications']['phones'])->toHaveCount(1)
            ->and(json_encode($export))->not->toContain('jamesIphone0001');

        $this->travel(91)->days();
        $this->artisan('reos:notifications:prune')->assertSuccessful();
        expect(DB::table('notification_deliveries')->count())->toBe(0);
    });

    it('deletes the inbox with the account', function () {
        $state = State::query()->firstOrCreate(['code' => 'LA'], ['name' => 'Louisiana', 'status' => 'live']);
        $this->james->notify(new StateWentLive($state));
        $this->james->forceFill(['deletion_requested_at' => now()->subDays(31)])->save();

        app(AccountDeletion::class)->purge();

        expect(DB::table('notifications')->count())->toBe(0)->and(PushDevice::query()->count())->toBe(0);
    });
});
