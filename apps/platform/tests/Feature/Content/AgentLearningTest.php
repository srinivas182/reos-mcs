<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\CourseStatus;
use App\Content\Player\SeatTime;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\Lesson;
use App\Models\Organisation;
use App\Models\RolePlay;
use App\Models\State;
use App\Models\User;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use App\Services\Licences\Licences;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Agent learning (A14.2, ADR 0062): course picks by renewal, practice and
 * mode, and audio-only lessons whose seat time counts only where the school
 * has confirmed it.
 */

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00', 'America/Chicago'));
    $this->ops = User::factory()->superAdmin()->create();
    $this->org = Organisation::factory()->create(['name' => 'Lone Star Realty']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->org)->create(['name' => 'Maria Garcia']);
    $this->seat = app(SeatTime::class);
});

/** A live one-lesson course for Texas. */
function learningCourse(User $ops, string $title, string $type = 'elective', array $fill = [], string $body = "# Why it matters\nAcknowledge the concern first.\n# What to say\nThen answer that exact concern with numbers."): Course
{
    $course = app(Courses::class)->create(['title' => $title, 'type' => $type, 'states' => ['TX'], 'hours' => 3], $ops);
    $lesson = app(Lessons::class)->create($course, ['title' => $title.': lesson 1'], $ops);
    app(Lessons::class)->update($lesson, ['body' => $body], $ops);
    $lesson->forceFill(['minutes' => 2])->save();
    $course->forceFill(['status' => CourseStatus::Live, ...$fill])->save();

    return $course->refresh();
}

function approve(Course $course, array $rules = []): CourseApproval
{
    return CourseApproval::query()->create(['course_id' => $course->id, 'state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'CE-'.Str::random(4), 'hours' => 3,
        'approved_on' => '2026-01-01', 'expires_on' => '2028-01-01', 'status' => CourseApproval::APPROVED, ...$rules]);
}

/** Heartbeats 15 seconds apart through the API, as the phone sends them. */
function listenBeats(object $test, Lesson $lesson, int $count, array $beat = []): array
{
    $state = [];
    for ($i = 0; $i < $count; $i++) {
        $state = $test->postJson("/api/v1/learn/lessons/{$lesson->id}/heartbeat", ['position' => $i * 15, 'playing' => true, 'visible' => false, 'session' => 'car-1', 'timezone' => 'America/Chicago', 'mode' => 'audio', ...$beat])->assertOk()->json('data');
        $test->travel(15)->seconds();
    }

    return $state;
}

describe('audio-only lessons (A14.2)', function () {
    it('plays a CE lesson in audio-only mode and counts seat time with the screen off (the done-when)', function () {
        $course = learningCourse($this->ops, 'Texas Legal Update I', 'ce');
        approve($course, ['audio_allowed' => true]);
        $lesson = $course->lessons()->firstOrFail();

        $listen = $this->actingAs($this->maria)->getJson("/api/v1/learn/lessons/{$lesson->id}/listen")->assertOk()->json('data');
        expect($listen)->toMatchArray(['title' => 'Texas Legal Update I: lesson 1', 'course' => 'Texas Legal Update I', 'audio_allowed' => true, 'words' => 17, 'seconds' => 7])
            ->and($listen['sections'][0])->toBe(['heading' => 'Why it matters', 'text' => 'Acknowledge the concern first.']);

        $state = listenBeats($this, $lesson, 5);
        expect($state)->toMatchArray(['counted' => 60, 'reason' => null])
            ->and(DB::table('seat_time_events')->where('lesson_id', $lesson->id)->where('source', 'app-audio')->sum('counted'))->toBe(60);
    });

    it("doesn't count audio-only CE until the school confirms it, and the same screen rules still apply on screen", function () {
        $course = learningCourse($this->ops, 'Texas Legal Update II', 'ce');
        $approval = approve($course);
        $lesson = $course->lessons()->firstOrFail();

        $this->actingAs($this->maria)->getJson("/api/v1/learn/lessons/{$lesson->id}/listen")->assertJsonPath('data.audio_allowed', false);
        expect(listenBeats($this, $lesson, 4))->toMatchArray(['counted' => 0, 'reason' => 'audio_not_allowed']);
        // On screen but hidden still doesn't count.
        expect(listenBeats($this, $lesson, 2, ['mode' => 'screen']))->toMatchArray(['counted' => 0, 'reason' => 'hidden']);

        // The school ticks "audio-only counts" on the approval.
        $approval->forceFill(['audio_allowed' => true])->save();
        $this->travel(1)->minutes();
        expect(listenBeats($this, $lesson, 3))->toMatchArray(['counted' => 30, 'reason' => null]);
    });

    it('always lets skills courses count in audio-only mode, and asks check-ins out loud', function () {
        $course = learningCourse($this->ops, 'Handling objections', 'elective');
        approve($course, ['checkin_minutes' => 1]);
        $lesson = $course->lessons()->firstOrFail();

        $this->actingAs($this->maria)->getJson("/api/v1/learn/lessons/{$lesson->id}/listen")->assertJsonPath('data.audio_allowed', true);
        $state = listenBeats($this, $lesson, 6);
        expect($state['reason'])->toBe('checkin_due')->and($state['checkin']['kind'])->toBe('presence');

        // "I'm here", answered by voice on the phone.
        $this->postJson("/api/v1/learn/lessons/{$lesson->id}/checkins", ['key' => $state['checkin']['key'], 'choice' => null])->assertOk();
        expect(listenBeats($this, $lesson, 2)['reason'])->toBeNull();
    });

});

describe('course picks (A14.2)', function () {
    it('puts CE first near renewal, then the skills practice found weak, then the mode', function () {
        [$licence] = app(Licences::class)->add($this->maria, State::query()->findOrFail('TX'), '0712345', null, null);
        $licence->forceFill(['expires_on' => now()->addDays(60)->toDateString()])->save();

        $ce = learningCourse($this->ops, 'Texas Legal Update I', 'ce');
        approve($ce, ['audio_allowed' => true]);
        $objections = learningCourse($this->ops, 'Handling objections', 'elective', ['skills' => ['objection_handling']]);
        $asking = learningCourse($this->ops, 'Asking for the next step', 'elective', ['skills' => ['asking_for_business']]);
        $launch = learningCourse($this->ops, 'Your first 90 days', 'elective', ['modes' => ['launch']]);
        learningCourse($this->ops, 'Team leadership', 'elective', ['modes' => ['lead']]);

        // The latest practice's fixes point to asking for the next step and objections.
        RolePlay::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->maria->id, 'type' => 'objection', 'persona' => 'rate_worried_buyer', 'language' => 'en',
            'turns' => [['role' => 'persona', 'text' => 'Hi']], 'status' => 'scored', 'score' => 40, 'scores' => ['structure' => 10, 'questions' => 10, 'objections' => 5, 'clarity' => 10, 'ask' => 5],
            'fixes' => [['dimension' => 'ask', 'text' => 'a', 'example' => 'b', 'lesson' => 'asking_for_business'], ['dimension' => 'objections', 'text' => 'a', 'example' => 'b', 'lesson' => 'objection_handling']],
            'engine' => 'script', 'finished_at' => now()]);

        $picks = $this->actingAs($this->maria)->getJson('/api/v1/learn/picks')->assertOk()->json('data');
        expect(array_column($picks, 'title'))->toBe(['Texas Legal Update I', 'Asking for the next step', 'Handling objections', 'Your first 90 days'])
            ->and(array_column($picks, 'reason'))->toBe(['renewal', 'skill', 'skill', 'mode'])
            ->and($picks[0]['audio'])->toBeTrue()
            ->and($picks[1])->toMatchArray(['skill' => 'asking_for_business', 'audio' => true, 'percent' => 0]);

        // A fix's lesson link asks for one skill first.
        $this->getJson('/api/v1/learn/picks?topic=objection_handling')->assertJsonPath('data.1.title', 'Handling objections');
        $this->getJson('/api/v1/learn/picks?topic=not_a_skill')->assertUnprocessable();

        // The web Learn page shows them; other roles get none.
        $this->get('/learn?topic=objection_handling')->assertOk()->assertInertia(fn (Assert $page) => $page->component('learn/index')->has('picks', 4)->where('topic', 'objection_handling'));
        $candidate = User::factory()->withRole(Role::Candidate)->create();
        $this->actingAs($candidate)->getJson('/api/v1/learn/picks')->assertOk()->assertJsonPath('data', []);
        unset($objections, $asking, $launch);
    });

    it('lets the content team say what a course teaches and who it suits', function () {
        $course = learningCourse($this->ops, 'Handling objections');
        $this->actingAs($this->ops)->patch("/admin/content/courses/{$course->id}", ['skills' => ['objection_handling', 'asking_for_business'], 'modes' => ['launch', 'grow']])->assertRedirect();
        expect($course->refresh()->skills)->toBe(['objection_handling', 'asking_for_business'])->and($course->modes)->toBe(['launch', 'grow']);

        $this->patch("/admin/content/courses/{$course->id}", ['skills' => '', 'modes' => ['own']])->assertRedirect();
        expect($course->refresh()->skills)->toBeNull()->and($course->modes)->toBe(['own']);
        $this->patch("/admin/content/courses/{$course->id}", ['skills' => ['juggling']])->assertSessionHasErrors('skills.0');
    });
});
