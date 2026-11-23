<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\CourseStatus;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Content\Video\MuxWebhooks;
use App\Identity\DeviceInfo;
use App\Models\CourseApproval;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\LessonProgress;
use App\Models\OfflineGrant;
use App\Models\User;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use App\Services\Identity\DeviceSessions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-23 15:00:00', 'UTC'));
    $this->ops = User::factory()->superAdmin()->create();
    $this->learner = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    $this->phone = (string) Str::uuid();
    $this->withToken(app(DeviceSessions::class)->issue($this->learner, new DeviceInfo($this->phone, "James's iPhone", 'ios'), 'phone', false)['token']);
});

/** A live one-lesson course with a ready video file, and the approval's rules when given. */
function offlineLesson(User $ops, int $minutes = 10, array $rules = []): Lesson
{
    $course = app(Courses::class)->create(['title' => 'Texas Agency Law Update', 'type' => 'ce', 'states' => ['TX'], 'hours' => 2], $ops);
    $lesson = app(Lessons::class)->create($course, ['title' => 'Agency disclosure'], $ops);
    $lesson->forceFill(['minutes' => $minutes])->save();
    $course->forceFill(['status' => CourseStatus::Live])->save();
    Storage::disk('local')->put("platform/lessons/{$lesson->id}/video.mp4", 'video');
    LessonMedia::query()->forceCreate([
        'lesson_id' => $lesson->id, 'kind' => MediaKind::Video, 'path' => "platform/lessons/{$lesson->id}/video.mp4", 'original_name' => 'agency.mp4',
        'mime' => 'video/mp4', 'size' => 90_000_000, 'status' => MediaStatus::Ready, 'duration_seconds' => $minutes * 60,
    ]);

    if ($rules !== []) {
        CourseApproval::query()->create(['course_id' => $course->id, 'state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'CE-1234', 'hours' => 2,
            'approved_on' => '2026-01-01', 'expires_on' => '2028-01-01', 'status' => CourseApproval::APPROVED, ...$rules]);
    }

    return $lesson->refresh();
}

/** Sends offline events signed with the grant's key, as the phone does. */
function sendOffline(object $test, array $saved, array $events, ?string $key = null): TestResponse
{
    $payload = json_encode($events, JSON_THROW_ON_ERROR);

    return $test->postJson("/api/v1/learn/downloads/{$saved['grant']}/seat-time", [
        'payload' => $payload, 'signature' => hash_hmac('sha256', $payload, $key ?? $saved['key']), 'timezone' => 'America/Chicago',
    ]);
}

/** A watched stretch so many minutes after the lesson was saved ($from). */
function watched(string $id, int $minutesIn, int $seconds, CarbonImmutable $from): array
{
    return ['id' => $id, 'type' => 'watch', 'at' => $from->addMinutes($minutesIn)->toIso8601String(), 'seconds' => $seconds];
}

describe('saving a lesson on the phone (A8.2b)', function () {
    it('saves a lesson with its download, the offline player and a key, for 30 days', function () {
        $lesson = offlineLesson($this->ops);

        $saved = $this->postJson("/api/v1/learn/lessons/{$lesson->id}/downloads")->assertCreated()
            ->assertJsonPath('data.bytes', 90_000_000)
            ->assertJsonPath('data.lesson.title', 'Agency disclosure')
            ->assertJsonPath('data.player.required', 600)
            ->assertJsonPath('data.expires_at', now()->addDays(30)->toIso8601String())
            ->json('data');

        expect($saved['url'])->toContain('/lesson-media/')->and(strlen($saved['key']))->toBe(64)
            ->and(OfflineGrant::query()->sole())->device->toBe($this->phone)->key->toBe($saved['key']);
        // The key is encrypted at rest.
        expect(DB::table('offline_grants')->value('key'))->not->toBe($saved['key']);

        $this->getJson('/api/v1/learn/downloads')->assertOk()->assertJsonPath('data.0.status', 'active')->assertJsonPath('data.0.title', 'Agency disclosure');
    });

    it('waits for Mux\'s 720p download before a Mux video can be saved', function () {
        $lesson = offlineLesson($this->ops);
        $lesson->media()->update(['mux_playback_id' => 'play-1', 'mux_asset_id' => 'asset-1']);
        // Mux isn't set up in tests, and the 720p download hasn't been reported ready.

        $this->postJson("/api/v1/learn/lessons/{$lesson->id}/downloads")->assertUnprocessable()->assertJsonValidationErrors('lesson');
    });

    it('works only on a signed-in phone, for lessons the learner can open', function () {
        $lesson = offlineLesson($this->ops);
        $other = offlineLesson($this->ops);
        $other->course->forceFill(['status' => CourseStatus::Draft])->save();

        $this->postJson("/api/v1/learn/lessons/{$other->id}/downloads")->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->learner->createToken('no device')->plainTextToken)->postJson("/api/v1/learn/lessons/{$lesson->id}/downloads")->assertForbidden();
    });

    it('stops working when access ends: revoked, and 410 on renewal', function () {
        $lesson = offlineLesson($this->ops);
        $saved = $this->postJson("/api/v1/learn/lessons/{$lesson->id}/downloads")->json('data');

        $this->travel(20)->days();
        $this->postJson("/api/v1/learn/downloads/{$saved['grant']}/renew")->assertOk()->assertJsonPath('expires_at', now()->addDays(30)->toIso8601String());

        $lesson->course->forceFill(['status' => CourseStatus::Hidden])->save();
        $this->postJson("/api/v1/learn/downloads/{$saved['grant']}/renew")->assertStatus(410);
        $this->getJson('/api/v1/learn/downloads')->assertJsonPath('data.0.status', 'revoked');
    });

    it('marks the Mux 720p download ready from the webhook', function () {
        $lesson = offlineLesson($this->ops);
        $media = $lesson->media()->sole();
        $media->forceFill(['mux_asset_id' => 'asset-9'])->save();

        (new ReflectionMethod(MuxWebhooks::class, 'handle'))->invoke(app(MuxWebhooks::class), 'video.asset.static_rendition.ready', ['asset_id' => 'asset-9', 'resolution' => '720p', 'filesize' => '123456789']);

        expect($media->refresh()->offline)->toBe(['resolution' => '720p', 'bytes' => 123456789]);
    });
});

describe('offline seat time, the A8.2 done-when', function () {
    it('counts the same however the events arrive: out of order, twice, or overlapping', function () {
        $lesson = offlineLesson($this->ops);
        $saved = $this->postJson("/api/v1/learn/lessons/{$lesson->id}/downloads")->json('data');
        $t = CarbonImmutable::now();
        $this->travel(1)->hours();
        $events = [watched('a', 10, 120, $t), watched('b', 13, 60, $t), watched('c', 1, 90, $t)];

        sendOffline($this, $saved, [$events[1], $events[2]])->assertOk()->assertJson(['accepted' => 2, 'rejected' => 0]);
        sendOffline($this, $saved, array_reverse($events))->assertOk()->assertJson(['accepted' => 1, 'duplicates' => 2])->assertJsonPath('state.counted', 270);

        // A stretch overlapping one already counted (11:00–12:00 inside 10:00–12:00) adds nothing.
        sendOffline($this, $saved, [watched('d', 11, 60, $t)])->assertJson(['accepted' => 1])->assertJsonPath('state.counted', 270);
        expect(DB::table('seat_time_events')->where('segment', 'like', '%:d')->value('reason'))->toBe('overlap');
    });

    it('refuses a wrong signature, and stretches from before the download or the future', function () {
        $lesson = offlineLesson($this->ops);
        $saved = $this->postJson("/api/v1/learn/lessons/{$lesson->id}/downloads")->json('data');
        $t = CarbonImmutable::now();

        sendOffline($this, $saved, [watched('a', 1, 60, $t)], key: 'not-the-key')->assertUnprocessable()->assertJsonValidationErrors('signature');
        sendOffline($this, $saved, [watched('early', -60, 60, $t), watched('late', 10, 60, $t)])->assertJson(['accepted' => 0, 'rejected' => 2])->assertJsonPath('state.counted', 0);
        // 15 minutes at most per stretch.
        $this->travel(1)->hours();
        sendOffline($this, $saved, [watched('long', 0, 3000, $t)])->assertJsonPath('state.counted', 900);
    });

    it('stops at a check-in until it is answered offline, and at the daily maximum', function () {
        $lesson = offlineLesson($this->ops, 30, ['checkin_minutes' => 5, 'max_hours_per_day' => 1]);
        $saved = $this->postJson("/api/v1/learn/lessons/{$lesson->id}/downloads")->json('data');
        expect($saved['checkpoints'][0])->toMatchArray(['key' => 'presence:1', 'at' => 300, 'kind' => 'presence', 'answer' => null]);
        $t = CarbonImmutable::now();
        $this->travel(2)->hours();

        // 10 minutes watched, but the check-in at 5 minutes waits: 5 count.
        sendOffline($this, $saved, [watched('a', 0, 600, $t)])->assertJsonPath('state.counted', 300);
        // Answered offline at 11 minutes, then 10 more minutes: 5 more count up to the next check-in.
        sendOffline($this, $saved, [watched('b', 12, 600, $t), ['id' => 'c1', 'type' => 'checkin', 'at' => $t->addMinutes(11)->toIso8601String(), 'key' => 'presence:1']])
            ->assertJsonPath('state.counted', 600);
        expect(LessonProgress::query()->sole()->checkins)->toHaveKey('presence:1');

        // The day's hour (3,600 s) is already nearly used.
        DB::table('seat_time_days')->where('user_id', $this->learner->id)->update(['seconds' => 3590]);
        sendOffline($this, $saved, [['id' => 'c2', 'type' => 'checkin', 'at' => $t->addMinutes(23)->toIso8601String(), 'key' => 'presence:2'], watched('d', 24, 300, $t)])
            ->assertJsonPath('state.counted', 610);
    });
});
