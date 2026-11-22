<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Events\Video\SessionEnded;
use App\Features\Flags;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VideoSession;
use App\Notifications\SessionBooked;
use App\Notifications\SessionStarting;
use App\Services\Privacy\DataExport;
use App\Video\VideoSessions;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(fn () => JWT::$timestamp = null);

beforeEach(function () {
    $flags = app(Flags::class);
    $flag = $flags->flag('video_rooms');
    $flag->forceFill(['stage' => 'everyone'])->save();
    $flags->changed($flag);

    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->rachel = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Rachel Hughes']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Sofia Reyes']);
    $this->marco = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Marco Diaz']);
    $this->stranger = User::factory()->withRole(Role::Candidate)->create(['name' => 'Someone Else']);
    $this->travelTo(CarbonImmutable::parse('2026-11-24 14:00', 'America/Chicago'));
});

function schedule(object $test, array $overrides = []): VideoSession
{
    $test->actingAs($test->rachel)->post('/sessions', [
        'kind' => 'one_on_one',
        'title' => 'Contract review',
        'starts_at' => CarbonImmutable::parse('2026-11-24 15:00', 'America/Chicago')->toIso8601String(),
        'minutes' => 45,
        'guests' => [$test->sofia->id],
        ...$overrides,
    ])->assertSessionHasNoErrors()->assertRedirect();

    return VideoSession::query()->latest('id')->firstOrFail();
}

function useLiveKit(): void
{
    IntegrationSetting::query()->create(['integration_id' => 'livekit', 'mode' => IntegrationMode::Sandbox]);
    app(CredentialVault::class)->put('livekit', CredentialEnvironment::Sandbox, ['server_url' => 'wss://reos-test.livekit.cloud', 'api_key' => 'APItest', 'api_secret' => str_repeat('s', 40)], null);
}

describe('scheduling', function () {
    it('lets instructors schedule with people they work with, and tells them', function () {
        Notification::fake();
        $session = schedule($this, ['agenda' => 'Section 4 of the TREC contract']);

        expect($session->ends_at->diffInMinutes($session->starts_at, true))->toBe(45.0)
            ->and($session->participants()->pluck('role', 'user_id')->all())->toBe([$this->rachel->id => 'host', $this->sofia->id => 'guest']);
        Notification::assertSentTo($this->sofia, SessionBooked::class, fn (SessionBooked $n) => $n->change === SessionBooked::BOOKED);
        Notification::assertNotSentTo($this->rachel, SessionBooked::class);
    });

    it('refuses strangers, the past, overlaps, two people in a 1-on-1 and non-hosts', function () {
        $at = CarbonImmutable::parse('2026-11-24 15:00', 'America/Chicago')->toIso8601String();
        $post = fn (User $as, array $data) => $this->actingAs($as)->post('/sessions', ['kind' => 'one_on_one', 'title' => 'x', 'starts_at' => $at, 'minutes' => 45, 'guests' => [$this->sofia->id], ...$data]);

        $post($this->rachel, ['guests' => [$this->stranger->id]])->assertSessionHasErrors('guests');
        $post($this->rachel, ['guests' => [$this->sofia->id, $this->marco->id]])->assertSessionHasErrors('guests');
        $post($this->rachel, ['starts_at' => '2026-11-20T10:00:00-06:00'])->assertSessionHasErrors('starts_at');
        $post($this->rachel, ['minutes' => 50])->assertSessionHasErrors('minutes');
        $post($this->sofia, ['guests' => [$this->rachel->id]])->assertSessionHasErrors('guests');

        schedule($this);
        $post($this->rachel, ['starts_at' => CarbonImmutable::parse('2026-11-24 15:30', 'America/Chicago')->toIso8601String()])->assertSessionHasErrors('starts_at');
    });

    it('shows sessions only to the people in them', function () {
        $session = schedule($this);

        $this->actingAs($this->sofia)->get('/sessions')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('sessions/index')
            ->where('canHost', false)
            ->where('sessions.0.title', 'Contract review')
            ->where('sessions.0.me', 'guest')
            ->where('sessions.0.can_join', false));
        $this->actingAs($this->rachel)->get('/sessions')->assertInertia(fn (Assert $page) => $page->where('canHost', true)->has('contacts', 2));
        $this->actingAs($this->stranger)->get("/sessions/{$session->uuid}")->assertNotFound();
        $this->actingAs($this->stranger, 'sanctum')->postJson("/api/v1/sessions/{$session->uuid}/join")->assertNotFound();
        $this->actingAs($this->sofia)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('sessions.next.id', $session->uuid));
    });
});

describe('the room', function () {
    it('done when: two people hold a 45-minute session that ends on time', function () {
        useLiveKit();
        Event::fake([SessionEnded::class]);
        Http::fake(['reos-test.livekit.cloud/twirp/*' => Http::response([])]);
        $session = schedule($this);

        // Too early: the room opens five minutes before.
        $this->actingAs($this->sofia, 'sanctum')->postJson("/api/v1/sessions/{$session->uuid}/join")->assertUnprocessable();

        $this->travelTo(CarbonImmutable::parse('2026-11-24 14:55', 'America/Chicago'));
        $rachel = $this->actingAs($this->rachel)->postJson("/sessions/{$session->uuid}/join")->assertOk()->json();
        $sofia = $this->actingAs($this->sofia, 'sanctum')->postJson("/api/v1/sessions/{$session->uuid}/join")->assertOk()
            ->assertJsonPath('simulated', false)
            ->assertJsonPath('url', 'wss://reos-test.livekit.cloud')
            ->json();

        // Each token is for one person in this room only, and stops at the booked end.
        JWT::$timestamp = now()->getTimestamp();
        $host = (array) JWT::decode($rachel['token'], new Key(str_repeat('s', 40), 'HS256'));
        $guest = (array) JWT::decode($sofia['token'], new Key(str_repeat('s', 40), 'HS256'));
        expect($guest['sub'])->toBe('user-'.$this->sofia->id)
            ->and((array) $guest['video'])->toMatchArray(['room' => 'reos-'.$session->uuid, 'roomJoin' => true, 'canPublish' => true, 'roomAdmin' => false])
            ->and(((array) $host['video'])['roomAdmin'])->toBeTrue()
            ->and($guest['exp'])->toBe(CarbonImmutable::parse('2026-11-24 15:45', 'America/Chicago')->getTimestamp())
            ->and($session->fresh()?->status)->toBe('live');

        // Still running at 3:44.
        $this->travelTo(CarbonImmutable::parse('2026-11-24 15:44', 'America/Chicago'));
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        expect($session->fresh()?->status)->toBe('live');

        // At 3:45 the room closes for everyone, on LiveKit and in every open screen.
        $this->travelTo(CarbonImmutable::parse('2026-11-24 15:45', 'America/Chicago'));
        $this->artisan('reos:sessions:tick')->assertSuccessful();

        expect($session->fresh())->status->toBe('ended')->ended_at->not->toBeNull();
        Http::assertSent(fn (Request $r) => $r->url() === 'https://reos-test.livekit.cloud/twirp/livekit.RoomService/DeleteRoom' && $r['room'] === 'reos-'.$session->uuid);
        Event::assertDispatched(SessionEnded::class, fn (SessionEnded $e) => $e->session->is($session) && $e->userIds === [$this->rachel->id, $this->sofia->id]);
        $this->actingAs($this->sofia, 'sanctum')->postJson("/api/v1/sessions/{$session->uuid}/join")->assertUnprocessable()->assertJsonValidationErrors('session');
    });

    it('lets only the host end it early, and only before it starts cancel it', function () {
        Notification::fake();
        $session = schedule($this);

        $this->actingAs($this->sofia)->post("/sessions/{$session->uuid}/cancel")->assertSessionHasErrors('session');
        $this->actingAs($this->rachel)->post("/sessions/{$session->uuid}/cancel")->assertRedirect('/sessions');
        expect($session->fresh()?->status)->toBe('cancelled');
        Notification::assertSentTo($this->sofia, SessionBooked::class, fn (SessionBooked $n) => $n->change === SessionBooked::CANCELLED);

        $second = schedule($this, ['starts_at' => CarbonImmutable::parse('2026-11-24 16:00', 'America/Chicago')->toIso8601String(), 'kind' => 'live_class', 'guests' => [$this->sofia->id, $this->marco->id]]);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 16:10', 'America/Chicago'));
        $this->actingAs($this->marco)->postJson("/sessions/{$second->uuid}/join")->assertOk()->assertJsonPath('simulated', true);
        $this->actingAs($this->marco)->post("/sessions/{$second->uuid}/end")->assertSessionHasErrors('session');
        $this->actingAs($this->rachel)->post("/sessions/{$second->uuid}/end")->assertRedirect();
        expect($second->fresh()?->status)->toBe('ended');
    });

    it('lets only the host publish in a live class', function () {
        useLiveKit();
        $session = schedule($this, ['kind' => 'live_class', 'guests' => [$this->sofia->id, $this->marco->id]]);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 15:00', 'America/Chicago'));

        $token = $this->actingAs($this->marco, 'sanctum')->postJson("/api/v1/sessions/{$session->uuid}/join")->json('token');
        JWT::$timestamp = now()->getTimestamp();
        $claims = (array) JWT::decode($token, new Key(str_repeat('s', 40), 'HS256'));

        expect(((array) $claims['video'])['canPublish'])->toBeFalse();
    });

    it('reminds everyone ten minutes before, even in quiet hours, once', function () {
        Notification::fake();
        $session = schedule($this, ['starts_at' => CarbonImmutable::parse('2026-11-24 22:30', 'America/Chicago')->toIso8601String()]);

        $this->travelTo(CarbonImmutable::parse('2026-11-24 22:19', 'America/Chicago'));
        $this->artisan('reos:sessions:tick');
        Notification::assertNotSentTo($this->sofia, SessionStarting::class);

        $this->travelTo(CarbonImmutable::parse('2026-11-24 22:21', 'America/Chicago'));
        $this->artisan('reos:sessions:tick');
        $this->artisan('reos:sessions:tick');
        Notification::assertSentToTimes($this->sofia, SessionStarting::class, 1);
        Notification::assertSentToTimes($this->rachel, SessionStarting::class, 1);
        expect((new SessionStarting($session))->kind()->urgent())->toBeTrue();
    });
});

describe('notes', function () {
    it('keeps one set of notes everyone in the session shares (screen 16)', function () {
        $session = schedule($this);

        $this->actingAs($this->sofia)->put("/sessions/{$session->uuid}/notes", ['notes' => 'Read section 4 before Friday.'])->assertSessionHasNoErrors();
        $this->actingAs($this->rachel, 'sanctum')->getJson("/api/v1/sessions/{$session->uuid}")
            ->assertJsonPath('notes', 'Read section 4 before Friday.')
            ->assertJsonPath('notes_updated_by', 'Sofia Reyes');
        $this->actingAs($this->stranger)->put("/sessions/{$session->uuid}/notes", ['notes' => 'x'])->assertNotFound();
    });

    it('is behind the video_rooms flag', function () {
        $flags = app(Flags::class);
        $flag = $flags->flag('video_rooms');
        $flag->forceFill(['stage' => 'off'])->save();
        $flags->changed($flag);

        $this->actingAs($this->rachel)->get('/sessions')->assertNotFound();
        $this->actingAs($this->rachel)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('sessions', null));
    });
});

it('puts sessions in Download my data', function () {
    $session = schedule($this);
    app(VideoSessions::class)->saveNotes($session, $this->sofia, 'Read section 4.');

    $export = app(DataExport::class)->for($this->sofia);

    expect($export['sessions'][0])->toMatchArray(['title' => 'Contract review', 'host' => 'Rachel Hughes', 'role' => 'guest', 'notes' => 'Read section 4.']);
});
