<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Features\Flags;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\ConsentRecord;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VideoRecording;
use App\Models\VideoSession;
use App\Privacy\ConsentPurpose;
use App\Services\Content\LessonFiles;
use App\Video\VideoSessions;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $flags = app(Flags::class);
    $flag = $flags->flag('video_rooms');
    $flag->forceFill(['stage' => 'everyone'])->save();
    $flags->changed($flag);

    $school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->rachel = User::factory()->withRole(Role::Instructor, $school)->create(['name' => 'Rachel Hughes']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $school)->create(['name' => 'Sofia Reyes']);
    $this->marco = User::factory()->withRole(Role::Candidate, $school)->create(['name' => 'Marco Diaz']);
    $this->travelTo(CarbonImmutable::parse('2026-11-24 15:00', 'America/Chicago'));

    $this->session = app(VideoSessions::class)->schedule($this->rachel, [
        'kind' => VideoSession::LIVE_CLASS, 'title' => 'Exam prep: contracts', 'agenda' => null,
        'starts_at' => CarbonImmutable::parse('2026-11-24 15:00', 'America/Chicago'), 'minutes' => 45, 'guests' => [$this->sofia->id, $this->marco->id],
    ]);
});

/** Joins the room as each person given. */
function enterRoom(object $test, User ...$people): void
{
    foreach ($people as $person) {
        $test->actingAs($person)->postJson("/sessions/{$test->session->uuid}/join")->assertOk();
    }
}

function askToRecord(object $test): string
{
    return $test->actingAs($test->rachel)->postJson("/sessions/{$test->session->uuid}/recordings")->assertCreated()->json('id');
}

/** LiveKit's webhook signature: a token signed with the API secret carrying the body's SHA-256. */
function livekitWebhook(object $test, array $event): TestResponse
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $token = JWT::encode(['iss' => 'APItest', 'sha256' => base64_encode(hash('sha256', $body, true)), 'exp' => now()->addMinutes(5)->getTimestamp()], str_repeat('s', 40), 'HS256');

    return $test->call('POST', '/webhooks/livekit', [], [], [], ['CONTENT_TYPE' => 'application/webhook+json', 'HTTP_AUTHORIZATION' => $token], $body);
}

describe('consent', function () {
    it('done when: recording starts only after everyone in the room agrees, each a consent record', function () {
        enterRoom($this, $this->rachel, $this->sofia, $this->marco);
        $id = askToRecord($this);

        $this->actingAs($this->sofia)->postJson("/sessions/{$this->session->uuid}/recordings/{$id}/answer", ['agreed' => true])->assertOk()
            ->assertJsonPath('status', 'asking')
            ->assertJsonPath('waiting', ['Marco Diaz']);
        $this->actingAs($this->marco, 'sanctum')->postJson("/api/v1/sessions/{$this->session->uuid}/recordings/{$id}/answer", ['agreed' => true])->assertOk()
            ->assertJsonPath('status', 'recording');

        $consents = ConsentRecord::query()->where('purpose', ConsentPurpose::SessionRecording->value)->where('subject', 'recording:'.$id)->where('granted', true)->pluck('user_id')->all();
        expect($consents)->toEqualCanonicalizing([$this->rachel->id, $this->sofia->id, $this->marco->id])
            ->and(DB::table('audit_events')->where('action', 'session.recording_started')->exists())->toBeTrue();
    });

    it('cancels on one "no", asks only the people in the room, and lets late joiners in only once they agree', function () {
        enterRoom($this, $this->rachel, $this->sofia);
        $id = askToRecord($this);
        $this->actingAs($this->sofia)->postJson("/sessions/{$this->session->uuid}/recordings/{$id}/answer", ['agreed' => false])->assertJsonPath('status', 'declined');

        // Someone joining while it's only being asked comes in and is asked too.
        $third = askToRecord($this);
        enterRoom($this, $this->marco);
        $this->actingAs($this->sofia)->postJson("/sessions/{$this->session->uuid}/recordings/{$third}/answer", ['agreed' => true])->assertJsonPath('status', 'asking')->assertJsonPath('waiting', ['Marco Diaz']);
        $this->actingAs($this->marco)->postJson("/sessions/{$this->session->uuid}/recordings/{$third}/stop")->assertJsonPath('status', 'declined');
        $this->actingAs($this->marco)->postJson("/sessions/{$this->session->uuid}/leave")->assertOk();

        // Marco has left the room, so Sofia's yes is enough this time.
        $second = askToRecord($this);
        $this->actingAs($this->sofia)->postJson("/sessions/{$this->session->uuid}/recordings/{$second}/answer", ['agreed' => true])->assertJsonPath('status', 'recording');

        $this->actingAs($this->marco)->postJson("/sessions/{$this->session->uuid}/join")->assertUnprocessable()->assertJsonValidationErrors('recording');
        $this->actingAs($this->marco)->postJson("/sessions/{$this->session->uuid}/recordings/{$second}/answer", ['agreed' => true])->assertOk();
        $this->actingAs($this->marco)->postJson("/sessions/{$this->session->uuid}/join")->assertOk();
    });

    it('lets only the host ask and anyone stop it', function () {
        enterRoom($this, $this->rachel, $this->sofia);
        $this->actingAs($this->sofia)->postJson("/sessions/{$this->session->uuid}/recordings")->assertJsonValidationErrors('recording');

        $id = askToRecord($this);
        $this->actingAs($this->sofia)->postJson("/sessions/{$this->session->uuid}/recordings/{$id}/answer", ['agreed' => true]);
        $this->actingAs($this->sofia)->postJson("/sessions/{$this->session->uuid}/recordings/{$id}/stop")->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('expires_at', CarbonImmutable::parse('2026-11-24 15:45', 'America/Chicago')->addDays(90)->utc()->toIso8601String());
    });

    it('stops when the session ends', function () {
        enterRoom($this, $this->rachel);
        askToRecord($this);
        expect(VideoRecording::query()->sole()->status)->toBe('recording');

        $this->travelTo(CarbonImmutable::parse('2026-11-24 15:45', 'America/Chicago'));
        $this->artisan('reos:sessions:tick')->assertSuccessful();

        expect(VideoRecording::query()->sole()->status)->toBe('ready')
            ->and($this->session->participants()->where('in_room', true)->count())->toBe(0);
    });
});

describe('LiveKit', function () {
    beforeEach(function () {
        IntegrationSetting::query()->create(['integration_id' => 'livekit', 'mode' => IntegrationMode::Sandbox]);
        app(CredentialVault::class)->put('livekit', CredentialEnvironment::Sandbox, ['server_url' => 'wss://reos-test.livekit.cloud', 'api_key' => 'APItest', 'api_secret' => str_repeat('s', 40)], null);
        config([
            'reos.content.disk' => 'recordings-s3',
            'filesystems.disks.recordings-s3' => ['driver' => 's3', 'key' => 'AKIAREOS', 'secret' => 'bucket-secret', 'region' => 'us-east-2', 'bucket' => 'reos-content'],
        ]);
        Http::fake([
            'reos-test.livekit.cloud/twirp/livekit.Egress/StartRoomCompositeEgress' => Http::response(['egressId' => 'EG_abc123']),
            'reos-test.livekit.cloud/twirp/*' => Http::response([]),
        ]);
    });

    it('records to REOS storage with LiveKit egress and finishes from its webhook', function () {
        enterRoom($this, $this->rachel);
        $id = askToRecord($this);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'StartRoomCompositeEgress')
            && $r['room_name'] === 'reos-'.$this->session->uuid
            && $r['file_outputs'][0]['filepath'] === "recordings/{$this->session->uuid}/{$id}.mp4"
            && $r['file_outputs'][0]['s3']['bucket'] === 'reos-content');

        $this->actingAs($this->rachel)->postJson("/sessions/{$this->session->uuid}/recordings/{$id}/stop")->assertJsonPath('status', 'stopping');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'StopEgress') && $r['egress_id'] === 'EG_abc123');

        // A forged webhook changes nothing.
        $this->call('POST', '/webhooks/livekit', [], [], [], ['HTTP_AUTHORIZATION' => 'forged'], '{"event":"egress_ended"}')->assertForbidden();

        livekitWebhook($this, ['event' => 'egress_ended', 'egressInfo' => ['egressId' => 'EG_abc123', 'status' => 'EGRESS_COMPLETE', 'fileResults' => [['filename' => 'x.mp4', 'size' => '73400320', 'duration' => '2640000000000']]]])->assertOk();

        expect(VideoRecording::query()->sole())
            ->status->toBe('ready')
            ->duration_seconds->toBe(2640)
            ->path->toBe("recordings/{$this->session->uuid}/{$id}.mp4");
    });

    it('keeps who is in the room from LiveKit\'s webhooks', function () {
        enterRoom($this, $this->rachel, $this->sofia);
        livekitWebhook($this, ['event' => 'participant_left', 'room' => ['name' => 'reos-'.$this->session->uuid], 'participant' => ['identity' => 'user-'.$this->sofia->id]])->assertOk();

        expect($this->session->participants()->where('in_room', true)->pluck('user_id')->all())->toBe([$this->rachel->id]);
    });
});

describe('playback and retention', function () {
    it('plays only to the people in the session and is deleted after 90 days', function () {
        enterRoom($this, $this->rachel);
        $id = askToRecord($this);
        $recording = VideoRecording::query()->sole();
        $path = "recordings/{$this->session->uuid}/{$id}.mp4";
        LessonFiles::disk()->put($path, 'mp4-bytes');
        $recording->forceFill(['status' => 'ready', 'path' => $path, 'expires_at' => now()->addDays(90)])->save();

        $url = $this->actingAs($this->sofia, 'sanctum')->getJson("/api/v1/sessions/{$this->session->uuid}")->json('recordings.0.play_url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();

        $this->travel(91)->days();
        $this->artisan('reos:recordings:prune')->assertSuccessful();

        expect($recording->fresh()?->status)->toBe('deleted')
            ->and(LessonFiles::disk()->exists($path))->toBeFalse()
            ->and(DB::table('audit_events')->where('action', 'session.recording_deleted')->exists())->toBeTrue();
    });

    it('can be deleted by anyone in the session, never by others', function () {
        $stranger = User::factory()->withRole(Role::Candidate)->create();
        enterRoom($this, $this->rachel);
        $id = askToRecord($this);
        $this->actingAs($this->rachel)->postJson("/sessions/{$this->session->uuid}/recordings/{$id}/stop");

        $this->actingAs($stranger)->delete("/sessions/{$this->session->uuid}/recordings/{$id}")->assertNotFound();
        $this->actingAs($this->sofia, 'sanctum')->deleteJson("/api/v1/sessions/{$this->session->uuid}/recordings/{$id}")->assertNoContent();
        $this->actingAs($this->rachel)->get("/sessions/{$this->session->uuid}")->assertInertia(fn ($page) => $page->where('session.recordings', []));
    });
});
