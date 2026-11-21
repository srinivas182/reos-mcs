<?php

declare(strict_types=1);

use App\Connections\ConnectionStatus;
use App\Connections\SimulatorSignature;
use App\Connections\SyncTrigger;
use App\Identity\DeviceInfo;
use App\Integrations\Credentials\CredentialCipher;
use App\Integrations\IntegrationMode;
use App\Jobs\SyncConnection;
use App\Models\AuditEvent;
use App\Models\CalendarEvent;
use App\Models\Connection;
use App\Models\IntegrationSetting;
use App\Models\OAuthAuthorization;
use App\Models\SyncRun;
use App\Models\User;
use App\Notifications\ConnectionNeedsReconnect;
use App\Services\Connections\ConnectionHealth;
use App\Services\Connections\SyncEngine;
use App\Services\Identity\DeviceSessions;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

const CALENDAR = 'google-workspace';

/** Signs in on the website, then walks the demo sign-in to the callback. Returns the callback response. */
function connectOnWeb(object $test, User $user, bool $allow = true, ?callable $tamper = null): TestResponse
{
    $start = $test->actingAs($user)->get('/connections/'.CALENDAR.'/connect?history=12m');
    $start->assertRedirect();
    $simulator = (string) $start->headers->get('Location');
    expect($simulator)->toContain('/connections/simulator/'.CALENDAR);

    parse_str((string) parse_url($simulator, PHP_URL_QUERY), $query);
    $test->get($simulator)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('connections/simulator')
        ->where('account', $user->email));

    $decision = [...$query, 'allow' => $allow ? '1' : '0'];
    if ($tamper !== null) {
        $decision = $tamper($decision);
    }

    $answer = $test->post('/connections/simulator/'.CALENDAR, $decision);
    $answer->assertRedirect();

    return $test->get((string) $answer->headers->get('Location'));
}

/** A phone token for this person; also drops the website sign-in the helper above left behind. */
function connectionsPhoneToken(User $user): string
{
    app('auth')->forgetGuards();

    return app(DeviceSessions::class)->issue($user, new DeviceInfo((string) Str::uuid(), 'iPhone 17', 'ios'), 'phone', false)['token'];
}

describe('vendor sign-in (OAuth with PKCE) on demo data', function () {
    it('connects Google Calendar from the website and syncs the demo week', function () {
        $maria = User::factory()->create(['email' => 'maria@example.com']);

        connectOnWeb($this, $maria)->assertRedirect('/dashboard')
            ->assertSessionHas('connection', ['outcome' => 'connected', 'integration' => CALENDAR, 'reason' => null, 'name' => 'Google Calendar and Gmail']);

        $connection = Connection::query()->sole();
        expect($connection->owner_type)->toBe('user')
            ->and($connection->owner_id)->toBe($maria->id)
            ->and($connection->account_label)->toBe('maria@example.com')
            ->and($connection->mode)->toBe(IntegrationMode::Simulator)
            ->and($connection->status)->toBe(ConnectionStatus::Active)
            ->and($connection->history()->value)->toBe('12m')
            ->and($connection->last_synced_at)->not->toBeNull()
            ->and(CalendarEvent::query()->where('connection_id', $connection->id)->count())->toBe(12)
            ->and(SyncRun::query()->sole()->outcome)->toBe('synced')
            ->and(AuditEvent::query()->where('action', 'connection.connected')->exists())->toBeTrue();
    });

    it('keeps tokens encrypted with the Integration Hub key', function () {
        $maria = User::factory()->create();
        connectOnWeb($this, $maria);

        $connection = Connection::query()->sole();
        $raw = DB::table('connections')->value('access_token');

        expect($raw)->not->toBe($connection->access_token)
            ->and(app(CredentialCipher::class)->decrypt((string) $raw))->toBe($connection->access_token)
            ->and($connection->toArray())->not->toHaveKeys(['access_token', 'refresh_token']);
    });

    it("can't replay a state, use a forged one or an expired one", function () {
        $maria = User::factory()->create();
        $callback = null;

        $start = $this->actingAs($maria)->get('/connections/'.CALENDAR.'/connect');
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);
        $answer = $this->post('/connections/simulator/'.CALENDAR, [...$query, 'allow' => '1']);
        $callback = (string) $answer->headers->get('Location');

        $this->get($callback)->assertSessionHas('connection.outcome', 'connected');
        $this->get($callback)->assertSessionHas('connection.reason', 'connections.errors.stateInvalid');
        expect(Connection::query()->count())->toBe(1);

        $forged = Str::uuid().'.'.str_repeat('a', 32);
        $this->get('/integrations/'.CALENDAR.'/callback?code=x&state='.$forged)->assertSessionHas('connection.reason', 'connections.errors.stateInvalid');

        $this->get('/connections/'.CALENDAR.'/connect');
        OAuthAuthorization::query()->whereNull('used_at')->update(['expires_at' => now()->subMinute()]);
        $expired = OAuthAuthorization::query()->whereNull('used_at')->sole();
        $state = $expired->id.'.'.substr(hash_hmac('sha256', "oauth-state:{$expired->id}", config()->string('app.key')), 0, 32);
        $this->get('/integrations/'.CALENDAR.'/callback?code=x&state='.$state)->assertSessionHas('connection.reason', 'connections.errors.stateInvalid');
    });

    it('refuses a code bound to another PKCE challenge', function () {
        $maria = User::factory()->create();

        connectOnWeb($this, $maria, tamper: fn (array $d) => [...$d, 'code_challenge' => str_repeat('A', 43)])
            ->assertSessionHas('connection.reason', 'connections.errors.refused');

        expect(Connection::query()->exists())->toBeFalse();
    });

    it("connects nothing when the person doesn't allow access", function () {
        connectOnWeb($this, User::factory()->create(), allow: false)
            ->assertSessionHas('connection', ['outcome' => 'cancelled', 'integration' => CALENDAR, 'reason' => 'connections.errors.cancelled', 'name' => 'Google Calendar and Gmail']);

        expect(Connection::query()->exists())->toBeFalse();
    });

    it('only shows the demo sign-in for a genuine sign-in to REOS’s own callback', function () {
        $maria = User::factory()->create();
        $start = $this->actingAs($maria)->get('/connections/'.CALENDAR.'/connect');
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->get('/connections/simulator/'.CALENDAR.'?'.http_build_query([...$query, 'redirect_uri' => 'https://evil.example/cb']))->assertStatus(400);
        $this->post('/connections/simulator/'.CALENDAR, [...$query, 'redirect_uri' => 'https://evil.example/cb', 'allow' => '1'])->assertStatus(400);
        $this->get('/connections/simulator/'.CALENDAR.'?'.http_build_query([...$query, 'state' => 'nonsense']))->assertStatus(400);
    });

    it('connects from the phone and returns to the app', function () {
        $maria = User::factory()->create();
        $url = $this->withToken(connectionsPhoneToken($maria))->postJson('/api/v1/connections', ['integration' => CALENDAR, 'history' => '3m'])
            ->assertOk()->json('authorization_url');

        // The browser sheet has no web session: the state carries who it is.
        $this->app['auth']->forgetGuards();
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
        $answer = $this->post('/connections/simulator/'.CALENDAR, [...$query, 'allow' => '1']);
        $this->get((string) $answer->headers->get('Location'))
            ->assertRedirect('reos://connections?outcome=connected&integration='.CALENDAR.'&connection='.Connection::query()->sole()->id);

        expect(Connection::query()->sole()->owner_id)->toBe($maria->id);
    });

    it("won't connect a tool that is switched off", function () {
        IntegrationSetting::query()->create(['integration_id' => CALENDAR, 'mode' => IntegrationMode::Off]);
        $maria = User::factory()->create();

        $this->withToken(connectionsPhoneToken($maria))->postJson('/api/v1/connections', ['integration' => CALENDAR])
            ->assertStatus(422)->assertJson(['message' => 'This tool can\'t be connected right now.']);
        $this->withToken(connectionsPhoneToken($maria))->postJson('/api/v1/connections', ['integration' => 'stripe'])->assertJsonValidationErrors('integration');
        $this->actingAs($maria)->get('/connections/'.CALENDAR.'/connect')->assertRedirect('/dashboard');
    });

    it('reconnecting the same account updates it rather than adding another', function () {
        $maria = User::factory()->create();
        connectOnWeb($this, $maria);
        connectOnWeb($this, $maria)->assertSessionHas('connection.outcome', 'connected');

        expect(Connection::query()->count())->toBe(1)
            ->and(AuditEvent::query()->where('action', 'connection.reconnected')->exists())->toBeTrue();
    });
});

describe('the API', function () {
    it('lists only your own connections, never tokens', function () {
        $maria = User::factory()->create();
        $james = User::factory()->create();
        connectOnWeb($this, $maria);

        $this->withToken(connectionsPhoneToken($james))->getJson('/api/v1/connections')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken(connectionsPhoneToken($maria))->getJson('/api/v1/connections')->assertOk()
            ->assertJsonPath('data.0.integration', CALENDAR)
            ->assertJsonPath('data.0.integration_name', 'Google Calendar and Gmail')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.demo', true)
            ->assertJsonMissingPath('data.0.access_token');
    });

    it('syncs on request, and nobody else can touch your connection', function () {
        $maria = User::factory()->create();
        $james = User::factory()->create();
        connectOnWeb($this, $maria);
        $id = Connection::query()->sole()->id;

        $this->withToken(connectionsPhoneToken($james))->postJson("/api/v1/connections/{$id}/sync")->assertNotFound();
        $this->withToken(connectionsPhoneToken($james))->deleteJson("/api/v1/connections/{$id}")->assertNotFound();

        Queue::fake();
        $this->withToken(connectionsPhoneToken($maria))->postJson("/api/v1/connections/{$id}/sync")->assertStatus(202);
        Queue::assertPushed(SyncConnection::class, fn (SyncConnection $job) => $job->connectionId === $id && $job->trigger === SyncTrigger::Manual);
    });

    it('disconnects: forgets the tokens and everything synced', function () {
        $maria = User::factory()->create();
        connectOnWeb($this, $maria);
        $id = Connection::query()->sole()->id;

        $this->withToken(connectionsPhoneToken($maria))->deleteJson("/api/v1/connections/{$id}")->assertNoContent();

        expect(Connection::query()->exists())->toBeFalse()
            ->and(CalendarEvent::query()->exists())->toBeFalse()
            ->and(AuditEvent::query()->where('action', 'connection.disconnected')->exists())->toBeTrue();
    });
});

describe('sync engine and health', function () {
    it('needs a reconnect when the tool refuses the token, and says so once', function () {
        Notification::fake();
        $maria = User::factory()->create();
        connectOnWeb($this, $maria);
        $connection = Connection::query()->sole();
        $connection->forceFill(['meta' => ['simulator_revoked' => true]])->save();

        expect(app(SyncEngine::class)->run($connection->fresh(), 'events', SyncTrigger::Scheduled))->toBe('failed');
        expect(app(SyncEngine::class)->run($connection->fresh(), 'events', SyncTrigger::Scheduled))->toBe('skipped');

        $connection->refresh();
        expect($connection->status)->toBe(ConnectionStatus::ReconnectNeeded)
            ->and($connection->last_error)->toBe('connections.errors.refused')
            ->and(CalendarEvent::query()->count())->toBe(12);
        Notification::assertSentToTimes($maria, ConnectionNeedsReconnect::class, 1);
    });

    it('needs a reconnect when the super admin moves the tool to another run mode', function () {
        Notification::fake();
        connectOnWeb($this, User::factory()->create());
        IntegrationSetting::query()->create(['integration_id' => CALENDAR, 'mode' => IntegrationMode::Sandbox]);

        app(SyncEngine::class)->run(Connection::query()->sole(), 'events', SyncTrigger::Scheduled);

        expect(Connection::query()->sole()->last_error)->toBe('connections.errors.modeChanged');
    });

    it("doesn't sync a tool that is switched off", function () {
        connectOnWeb($this, User::factory()->create());
        IntegrationSetting::query()->create(['integration_id' => CALENDAR, 'mode' => IntegrationMode::Off]);

        expect(app(SyncEngine::class)->run(Connection::query()->sole(), 'events', SyncTrigger::Scheduled))->toBe('skipped')
            ->and(SyncRun::query()->count())->toBe(1);
    });

    it('marks a connection failing after three failed runs, and active again on success', function () {
        connectOnWeb($this, User::factory()->create());
        $connection = Connection::query()->sole();
        $health = app(ConnectionHealth::class);

        foreach (range(1, 3) as $ignored) {
            (new SyncConnection($connection->id, 'events', SyncTrigger::Scheduled))->failed(new RuntimeException('down'));
        }
        expect($connection->fresh()?->status)->toBe(ConnectionStatus::Failing);

        app(SyncEngine::class)->run($connection->fresh(), 'events', SyncTrigger::Scheduled);
        expect($connection->fresh()?->status)->toBe(ConnectionStatus::Active)
            ->and($connection->fresh()?->consecutive_failures)->toBe(0);
    });

    it('is idempotent: syncing again changes nothing', function () {
        connectOnWeb($this, User::factory()->create());
        $connection = Connection::query()->sole();
        $connection->forceFill(['cursors' => []])->save();

        app(SyncEngine::class)->run($connection->fresh(), 'events', SyncTrigger::Manual);

        expect(CalendarEvent::query()->count())->toBe(12);
    });

    it('queues due connections every 15 minutes and skips fresh ones', function () {
        connectOnWeb($this, User::factory()->create());
        connectOnWeb($this, User::factory()->create());
        Connection::query()->orderBy('id')->first()?->forceFill(['last_synced_at' => now()->subHour()])->save();

        Queue::fake();
        $this->artisan('reos:connections:sync')->expectsOutput('Queued 1 syncs.')->assertSuccessful();
        Queue::assertPushed(SyncConnection::class, 1);
    });

    it('simulates a revoked connection for demos', function () {
        connectOnWeb($this, User::factory()->create());
        $id = Connection::query()->sole()->id;

        $this->artisan('reos:connections:simulate', ['connection' => $id, '--revoke' => true])->assertSuccessful();
        expect(Connection::query()->sole()->meta['simulator_revoked'])->toBeTrue();
    });

    it('re-encrypts tokens when the hub key is rotated', function () {
        connectOnWeb($this, User::factory()->create());
        $before = DB::table('connections')->value('access_token');
        $token = Connection::query()->sole()->access_token;

        $this->artisan('reos:credentials:rekey')->assertSuccessful();

        expect(DB::table('connections')->value('access_token'))->not->toBe($before)
            ->and(Connection::query()->sole()->access_token)->toBe($token);
    });
});

describe('inbound webhooks', function () {
    it('queues a sync for a genuine delivery, once', function () {
        connectOnWeb($this, User::factory()->create());
        $id = Connection::query()->sole()->id;
        $body = json_encode(['connection_id' => $id, 'delivery_id' => 'delivery-1'], JSON_THROW_ON_ERROR);

        Queue::fake();
        $send = fn (string $signature) => $this->call('POST', '/webhooks/'.CALENDAR, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_REOS_SIMULATOR_SIGNATURE' => $signature,
        ], $body);

        $send(SimulatorSignature::sign($body))->assertStatus(202);
        $send(SimulatorSignature::sign($body))->assertOk()->assertJson(['duplicate' => true]);
        $send('forged')->assertStatus(401);

        Queue::assertPushed(SyncConnection::class, 1);
        Queue::assertPushed(SyncConnection::class, fn (SyncConnection $job) => $job->trigger === SyncTrigger::Webhook);
    });

    it('refuses webhooks for a tool that is off, and needs no CSRF token or session', function () {
        IntegrationSetting::query()->create(['integration_id' => CALENDAR, 'mode' => IntegrationMode::Off]);

        $this->postJson('/webhooks/'.CALENDAR, [])->assertStatus(401);
        $this->postJson('/webhooks/stripe', [])->assertNotFound();
    });
});

describe('reconnect prompts (B3.1b)', function () {
    it('shows the web app a connection that needs reconnecting, and clears it after signing in again', function () {
        Notification::fake();
        $james = User::factory()->create();
        connectOnWeb($this, $james);
        $connection = Connection::query()->sole();
        $connection->forceFill(['meta' => ['simulator_revoked' => true]])->save();
        app(SyncEngine::class)->run($connection->fresh(), 'events', SyncTrigger::Scheduled);

        $this->actingAs($james)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('connectionAlerts', 1)
            ->where('connectionAlerts.0.integration', CALENDAR)
            ->where('connectionAlerts.0.name', 'Google Calendar and Gmail'));
        $this->withToken(connectionsPhoneToken($james))->getJson('/api/v1/connections')
            ->assertJsonPath('data.0.status', 'reconnect_needed')
            ->assertJsonPath('data.0.problem', 'connections.errors.refused');

        app('auth')->forgetGuards();
        connectOnWeb($this, $james)->assertSessionHas('connection.outcome', 'connected');

        expect(Connection::query()->sole()->status)->toBe(ConnectionStatus::Active)
            ->and(Connection::query()->sole()->meta)->not->toHaveKey('simulator_revoked');
        $this->actingAs($james)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('connectionAlerts', 0));
    });

    it("shows nobody else's alerts", function () {
        Notification::fake();
        connectOnWeb($this, User::factory()->create());
        Connection::query()->update(['status' => ConnectionStatus::ReconnectNeeded]);

        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('connectionAlerts', 0));
    });

    it('seeds demo connections: Linda syncing, James needing a reconnect', function () {
        $this->seed(DemoSeeder::class);

        $statuses = Connection::query()->get()->mapWithKeys(fn (Connection $c) => [(string) $c->account_label => $c->status->value]);
        expect($statuses->filter(fn ($s, $email) => str_starts_with($email, 'linda.park@'))->first())->toBe('active')
            ->and($statuses->filter(fn ($s, $email) => str_starts_with($email, 'james.miller@'))->first())->toBe('reconnect_needed')
            ->and(CalendarEvent::query()->count())->toBe(24);
    });
});

describe('the phone screens’ data (B3.2b)', function () {
    it('says what REOS can and never does, and what syncs with how many records', function () {
        $maria = User::factory()->create();
        connectOnWeb($this, $maria);

        $catalogue = collect($this->withToken(connectionsPhoneToken($maria))->getJson('/api/v1/catalogue')->json('data'))->keyBy('id');
        expect($catalogue['google-workspace']['consent'])->toBe(['can' => ['read_events', 'change_alerts'], 'never' => ['change_events']])
            ->and($catalogue['zoom']['consent'])->toBeNull();

        $this->withToken(connectionsPhoneToken($maria))->getJson('/api/v1/connections')
            ->assertJsonPath('data.0.streams', [['key' => 'events', 'direction' => 'read', 'records' => 12]]);
    });

    it('describes each tool in the person’s language', function () {
        $maria = User::factory()->create();

        $catalogue = collect($this->withToken(connectionsPhoneToken($maria))->withHeader('Accept-Language', 'es-US')->getJson('/api/v1/catalogue')->json('data'))->keyBy('id');
        expect($catalogue['follow-up-boss']['purpose'])->toBe('Contactos, notas y tareas, en ambos sentidos.')
            ->and($catalogue->pluck('purpose')->filter(fn ($p) => str_starts_with((string) $p, 'integrations.')))->toBeEmpty();

        // The app sends the language on its screen.
        $english = $this->withToken(connectionsPhoneToken($maria))->withHeader('Accept-Language', 'en')->getJson('/api/v1/catalogue');
        expect(collect($english->json('data'))->keyBy('id')['follow-up-boss']['purpose'])->toBe('Contacts, notes and tasks, both ways.');
    });

    it('changes how far back it syncs, and syncs again from there', function () {
        $maria = User::factory()->create();
        connectOnWeb($this, $maria);
        $connection = Connection::query()->sole();
        expect($connection->cursor('events'))->not->toBeNull();

        Queue::fake();
        $this->withToken(connectionsPhoneToken($maria))->patchJson("/api/v1/connections/{$connection->id}", ['history' => 'all'])
            ->assertOk()->assertJsonPath('data.history', 'all');

        expect($connection->fresh()?->cursor('events'))->toBeNull();
        Queue::assertPushed(SyncConnection::class, fn (SyncConnection $job) => $job->trigger === SyncTrigger::Manual);

        $this->withToken(connectionsPhoneToken($maria))->patchJson("/api/v1/connections/{$connection->id}", ['history' => '5y'])->assertJsonValidationErrors('history');
        $this->withToken(connectionsPhoneToken(User::factory()->create()))->patchJson("/api/v1/connections/{$connection->id}", ['history' => '3m'])->assertNotFound();
    });
});
