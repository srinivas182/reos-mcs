<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\CourseStatus;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Models\LessonVersion;
use App\Models\Organisation;
use App\Models\QuizQuestion;
use App\Models\ReadinessSnapshot;
use App\Models\ReviewItem;
use App\Models\TestAttempt;
use App\Models\User;
use App\Notifications\WeeklyProgress;
use App\Practice\StudyPlans;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    // Sunday 29 November 2026, 6 pm in Texas: the end of the week of 23 November.
    $this->travelTo(CarbonImmutable::parse('2026-11-29 18:00', StudyPlans::TIMEZONE));
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $this->school)->create([
        'name' => 'Sofia Reyes', 'onboarding_path' => OnboardingPath::BecomeAgent->value, 'exam_month' => '2027-03-01',
        'minutes_per_day' => 20, 'reminder_time' => '19:30', 'onboarded_at' => now(),
    ]);
});

/** Sofia's week: two days of lessons, one 30-minute test, readiness 58% then 66%. */
function sofiaWeek(object $test): void
{
    $ops = User::factory()->superAdmin()->create();
    $course = app(Courses::class)->create(['title' => 'Principles of Real Estate I', 'type' => 'qualifying', 'states' => ['TX']], $ops);
    foreach (['2026-11-23' => 1500, '2026-11-25' => 900, '2026-11-20' => 600] as $day => $seconds) {
        DB::table('seat_time_days')->insert(['user_id' => $test->sofia->id, 'course_id' => $course->id, 'day' => $day, 'seconds' => $seconds, 'created_at' => now(), 'updated_at' => now()]);
    }
    TestAttempt::query()->create(['user_id' => $test->sofia->id, 'kind' => 'topic', 'mode' => 'practice', 'state_code' => 'TX', 'question_ids' => [1, 2, 3], 'answers' => [1 => 0, 2 => 1, 3 => 2], 'total' => 3,
        'started_at' => CarbonImmutable::parse('2026-11-26 19:00', StudyPlans::TIMEZONE), 'submitted_at' => CarbonImmutable::parse('2026-11-26 19:30', StudyPlans::TIMEZONE), 'by_topic' => ['finance' => ['correct' => 1, 'total' => 3]]]);
    ReadinessSnapshot::query()->create(['user_id' => $test->sofia->id, 'day' => '2026-11-22', 'percent' => 58, 'topics' => [], 'weakest' => []]);
    ReadinessSnapshot::query()->create(['user_id' => $test->sofia->id, 'day' => '2026-11-28', 'percent' => 66, 'topics' => [], 'weakest' => []]);
}

describe('the weekly progress report (screen 55, A9.2e)', function () {
    it('sums the week: time studied by day against the plan, questions, readiness change, hours and the streak', function () {
        sofiaWeek($this);

        $report = $this->actingAs($this->sofia)->getJson('/api/v1/practice/reports/weekly')->assertOk()->json('data.report');

        expect($report)->toMatchArray([
            'week_start' => '2026-11-23', 'week_end' => '2026-11-29', 'first_name' => 'Sofia',
            'minutes' => 70, 'goal_minutes' => 140, 'per_day' => [25, 0, 15, 30, 0, 0, 0], 'questions' => 3,
            'readiness' => ['now' => 66, 'before' => 58, 'change' => 8], 'hours' => 0.7, 'weakest' => 'finance',
        ]);
        $this->getJson('/api/v1/practice/reports/weekly?week=2026-11-18')->assertJsonPath('data.report.week_start', '2026-11-16')->assertJsonPath('data.report.minutes', 10);
        $this->get('/practice/progress')->assertOk()->assertInertia(fn (Assert $page) => $page->component('practice/progress')->where('report.minutes', 70)->where('shares.can_share_school', true));
    });

    it('is sent on Sunday evening to candidates who studied, by their notification settings', function () {
        Notification::fake();
        sofiaWeek($this);
        $idle = User::factory()->withRole(Role::Candidate)->create(['onboarded_at' => now()]);

        $this->artisan('reos:reports:weekly')->expectsOutputToContain('Sent 1 weekly reports.')->assertSuccessful();

        Notification::assertSentTo($this->sofia, WeeklyProgress::class, fn (WeeklyProgress $n) => $n->report['minutes'] === 70
            && $n->inbox($this->sofia)['body'] === '70 minutes studied, 3 questions answered, a 0-day streak. Readiness: 66% (+8 this week).'
            && $n->appPath() === '/practice/progress?week=2026-11-23');
        Notification::assertNotSentTo($idle, WeeklyProgress::class);
    });

    it('is shared with the school only when the candidate chooses, and by a first-name link for a study buddy', function () {
        sofiaWeek($this);

        $this->actingAs($this->paul)->get('/school/students')->assertOk()->assertInertia(fn (Assert $page) => $page->component('school/students')->has('reports', 0));

        $this->actingAs($this->sofia)->postJson('/api/v1/practice/reports/weekly/share', ['with' => 'school'])->assertOk()->assertJsonPath('data.school', true);
        $link = $this->postJson('/api/v1/practice/reports/weekly/share', ['with' => 'link'])->assertOk()->json('data.link');
        $this->postJson('/api/v1/practice/reports/weekly/share', ['with' => 'school'])->assertOk();

        $this->actingAs($this->paul)->get('/school/students')->assertInertia(fn (Assert $page) => $page->has('reports', 1)->where('reports.0.name', 'Sofia Reyes')->where('reports.0.report.minutes', 70));

        app('auth')->forgetGuards();
        $this->get(parse_url($link, PHP_URL_PATH))->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/report')->where('report.first_name', 'Sofia')->missing('report.name'));
        $this->travel(15)->days();
        $this->get(parse_url($link, PHP_URL_PATH))->assertNotFound();

        $alone = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['onboarded_at' => now(), 'onboarding_path' => OnboardingPath::BecomeAgent->value, 'exam_month' => '2027-03-01']);
        $this->actingAs($alone)->postJson('/api/v1/practice/reports/weekly/share', ['with' => 'school'])->assertUnprocessable();
    });
});

describe('Ask why (answer review, screen 45)', function () {
    it('opens the lesson behind a missed question with the question asked', function () {
        $ops = User::factory()->superAdmin()->create();
        $course = app(Courses::class)->create(['title' => 'Principles of Real Estate I', 'type' => 'qualifying', 'states' => ['TX']], $ops);
        $lesson = app(Lessons::class)->create($course, ['title' => 'Mortgage insurance'], $ops);
        $course->forceFill(['status' => CourseStatus::Live])->save();
        $version = LessonVersion::query()->create(['lesson_id' => $lesson->id, 'version' => 1, 'status' => 'ready', 'sources' => [], 'presenter' => 'stock', 'vendor' => 'simulated']);
        $item = ReviewItem::query()->create(['lesson_id' => $lesson->id, 'lesson_version_id' => $version->id, 'kind' => 'question', 'topic' => 'finance', 'status' => ReviewItem::APPROVED, 'content' => []]);
        $question = QuizQuestion::query()->where('topic', 'finance')->firstOrFail();
        $question->forceFill(['review_item_id' => $item->id])->save();
        $attempt = TestAttempt::query()->create(['user_id' => $this->sofia->id, 'kind' => 'topic', 'mode' => 'practice', 'state_code' => 'TX', 'question_ids' => [$question->id], 'answers' => [], 'total' => 1, 'started_at' => now()->subHour()]);

        $this->actingAs($this->sofia)->getJson("/api/v1/practice/attempts/{$attempt->id}/why/{$question->id}")->assertStatus(409);
        $attempt->forceFill(['submitted_at' => now()])->save();

        $why = $this->getJson("/api/v1/practice/attempts/{$attempt->id}/why/{$question->id}")->assertOk()->json('data');
        expect($why['lesson_id'])->toBe($lesson->id)
            ->and($why['question'])->toBe('Question: '.$question->in('en')['question'].' Why is “'.$question->in('en')['options'][$question->correct_option].'” the right answer?');

        $this->get("/learn/lessons/{$lesson->id}?ask=".urlencode($why['question']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('at.ask', $why['question']));
        $other = QuizQuestion::query()->where('topic', 'agency')->firstOrFail();
        $this->getJson("/api/v1/practice/attempts/{$attempt->id}/why/{$other->id}")->assertOk()->assertJsonPath('data', null);
    });
});
