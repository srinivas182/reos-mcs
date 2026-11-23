<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\CourseStatus;
use App\Content\Player\SeatTime;
use App\Models\CeCompletion;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\CourseProgress;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->ops = User::factory()->superAdmin()->create();
    $this->learner = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    $this->seat = app(SeatTime::class);
    $this->t0 = CarbonImmutable::parse('2026-11-23 15:00:00', 'UTC');
});

/** A live one-lesson course of so many minutes, with an approval's seat-time rules when given. */
function seatLesson(User $ops, int $minutes = 2, string $type = 'ce', array $rules = []): Lesson
{
    $course = app(Courses::class)->create(['title' => 'Texas Agency Law Update', 'type' => $type, 'states' => ['TX'], 'hours' => 2], $ops);
    $lesson = app(Lessons::class)->create($course, ['title' => 'Agency disclosure'], $ops);
    app(Lessons::class)->update($lesson, ['body' => 'Disclose agency at first substantive contact.'], $ops);
    $lesson->forceFill(['minutes' => $minutes])->save();
    $course->forceFill(['status' => CourseStatus::Live])->save();

    if ($rules !== []) {
        CourseApproval::query()->create(['course_id' => $course->id, 'state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'CE-1234', 'hours' => 2,
            'approved_on' => '2026-01-01', 'expires_on' => '2028-01-01', 'status' => CourseApproval::APPROVED, ...$rules]);
    }

    return $lesson->refresh();
}

/** Heartbeats every $every seconds for $count beats; returns the last state. */
function beats(object $test, Lesson $lesson, int $count, int $every = 15, array $beat = [], int $from = 0): array
{
    $state = [];
    for ($i = 0; $i < $count; $i++) {
        $state = $test->seat->beat($test->learner, $lesson, [
            'position' => ($from + $i) * $every, 'playing' => true, 'visible' => true, 'session' => 'v1', 'timezone' => 'America/Chicago', 'source' => 'web', ...$beat,
        ], $test->t0->addSeconds(($from + $i) * $every));
    }

    return $state;
}

describe('seat time, the A8.2 done-when', function () {
    it('counts only real time while the video plays with the page in front', function () {
        $lesson = seatLesson($this->ops, 10);

        expect(beats($this, $lesson, 5))->toMatchArray(['counted' => 60, 'required' => 600, 'reason' => null])
            ->and(beats($this, $lesson, 2, beat: ['playing' => false], from: 5))->toMatchArray(['counted' => 60, 'reason' => 'paused'])
            ->and(beats($this, $lesson, 2, beat: ['visible' => false], from: 7))->toMatchArray(['counted' => 60, 'reason' => 'hidden']);

        $events = DB::table('seat_time_events')->where('lesson_id', $lesson->id)->get();
        expect($events->where('reason', null)->sum('counted'))->toBe(60)
            ->and($events->where('reason', 'paused')->sum('uncounted'))->toBe(30)
            ->and($events->where('reason', 'hidden')->sum('uncounted'))->toBe(30);
    });

    it('never counts more than 20 seconds a beat, so speeding up adds nothing, and a long silence starts again', function () {
        $lesson = seatLesson($this->ops, 10);

        // Beats a minute apart with the video racing ahead: each counts nothing (a gap)...
        expect(beats($this, $lesson, 3, every: 60))->toMatchArray(['counted' => 0, 'reason' => 'gap']);
        // ...beats 40 seconds apart (2× speed claimed) count at most 20 each.
        expect(beats($this, $lesson, 4, every: 40, from: 10))->toMatchArray(['counted' => 60]);
    });

    it('stops counting at a check-in until it is answered', function () {
        $lesson = seatLesson($this->ops, 3, rules: ['checkin_minutes' => 1]);

        $state = beats($this, $lesson, 5);
        expect($state['counted'])->toBe(60)
            ->and($state['checkin'])->toMatchArray(['key' => 'presence:1', 'kind' => 'presence', 'at' => 60]);
        expect(beats($this, $lesson, 2, from: 5))->toMatchArray(['counted' => 60, 'reason' => 'checkin_due']);

        $this->actingAs($this->learner)->postJson("/learn/lessons/{$lesson->id}/checkins", ['key' => 'presence:1'])->assertOk()
            ->assertJsonPath('state.checkin', null);
        expect(beats($this, $lesson, 3, from: 7))->toMatchArray(['counted' => 105, 'reason' => null]);
    });

    it('stops at the approval\'s daily maximum, on the learner\'s own day', function () {
        $lesson = seatLesson($this->ops, 120, rules: ['max_hours_per_day' => 1]);
        // 59½ minutes of this course already counted today (Chicago): 30 seconds left.
        DB::table('seat_time_days')->insert(['user_id' => $this->learner->id, 'course_id' => $lesson->course_id, 'day' => '2026-11-23', 'seconds' => 3570, 'created_at' => now(), 'updated_at' => now()]);

        expect(beats($this, $lesson, 5))->toMatchArray(['counted' => 30, 'reason' => 'daily_max', 'today_left' => 0]);

        // Next day in Chicago, it counts again.
        $this->t0 = $this->t0->addDay();
        expect(beats($this, $lesson, 3))->toMatchArray(['counted' => 60, 'reason' => null]);
    });

    it('completes the lesson and the CE course, and records the CE completion', function () {
        $lesson = seatLesson($this->ops, 1);

        $state = beats($this, $lesson, 6);
        expect($state)->toMatchArray(['counted' => 75, 'required' => 60, 'completed' => true, 'percent' => 100])
            ->and(CourseProgress::query()->sole())->percent->toBe(100)->lessons_done->toBe(1)->completed_at->not->toBeNull()
            ->and(CeCompletion::query()->where('user_id', $this->learner->id)->sole())->title->toBe('Texas Agency Law Update')->source->toBe('reos');
    });

    it('shows progress on the lesson page and Learn, and takes heartbeats over the API', function () {
        $lesson = seatLesson($this->ops, 10, 'test_prep');

        $this->actingAs($this->learner)->get("/learn/lessons/{$lesson->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('learn/lesson')
            ->where('player.counted', 0)
            ->where('player.required', 600));

        $this->postJson("/learn/lessons/{$lesson->id}/heartbeat", ['position' => 0, 'playing' => true, 'visible' => true, 'session' => 'web-1'])->assertOk()
            ->assertJsonPath('data.counted', 0);
        $this->postJson("/api/v1/learn/lessons/{$lesson->id}/heartbeat", ['position' => 'x'])->assertUnprocessable();

        expect(LessonProgress::query()->sole()->session)->toBe('web-1');
    });

    it('takes no heartbeats for a lesson the learner can\'t open', function () {
        $lesson = seatLesson($this->ops);
        $lesson->course->forceFill(['status' => CourseStatus::Draft])->save();

        $this->actingAs($this->learner)->postJson("/learn/lessons/{$lesson->id}/heartbeat", ['position' => 0, 'playing' => true, 'visible' => true, 'session' => 'x'])->assertNotFound();
    });
});
