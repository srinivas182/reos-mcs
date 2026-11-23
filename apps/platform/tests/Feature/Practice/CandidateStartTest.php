<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\CourseStatus;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Models\CalendarEvent;
use App\Models\Lesson;
use App\Models\Organisation;
use App\Models\QuizQuestion;
use App\Models\StudyPlan;
use App\Models\StudyTask;
use App\Models\TestAttempt;
use App\Models\User;
use App\Practice\StudyPlans;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    // Monday 23 November 2026, 9 am in Texas.
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', StudyPlans::TIMEZONE));
    $this->ops = User::factory()->superAdmin()->create();
    $school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $school)->create([
        'name' => 'Sofia Reyes', 'onboarding_path' => OnboardingPath::BecomeAgent->value, 'exam_month' => '2027-03-01',
        'minutes_per_day' => 20, 'reminder_time' => '19:30', 'onboarded_at' => now(),
    ]);
    $course = app(Courses::class)->create(['title' => 'Principles of Real Estate I', 'type' => 'qualifying', 'states' => ['TX']], $this->ops);
    foreach (['Agency basics' => 25, 'Disclosures' => 15] as $title => $minutes) {
        $lesson = app(Lessons::class)->create($course, ['title' => $title], $this->ops);
        app(Lessons::class)->update($lesson, ['body' => "Body of {$title}."], $this->ops);
        $lesson->forceFill(['minutes' => $minutes])->save();
    }
    $course->forceFill(['status' => CourseStatus::Live])->save();
    $this->first = Lesson::query()->where('title', 'Agency basics')->sole();
});

/** Answers a test: right for the given topics, wrong for the rest. */
function answerTest(object $test, TestAttempt $attempt, array $rightTopics): void
{
    foreach (QuizQuestion::query()->whereIn('id', $attempt->question_ids)->get() as $question) {
        $choice = in_array($question->topic->value, $rightTopics, true) ? $question->correct_option : ($question->correct_option + 1) % 4;
        $test->putJson("/practice/attempts/{$attempt->id}/answers/{$question->id}", ['choice' => $choice])->assertOk();
    }
}

describe('the Texas diagnostic (decision 4)', function () {
    it('is 50 questions across the exam outline in 45 minutes, saved after every answer, with no feedback until the end', function () {
        $this->actingAs($this->sofia)->post('/practice/tests', ['kind' => 'diagnostic'])->assertRedirect();
        $attempt = TestAttempt::query()->sole();

        expect($attempt)->total->toBe(50)->mode->toBe('exam')
            ->and((int) $attempt->started_at->diffInMinutes($attempt->ends_at))->toBe(45)
            ->and(QuizQuestion::query()->whereIn('id', $attempt->question_ids)->get()->countBy(fn ($q) => $q->topic->value)->sortKeys()->all())
            ->toEqual(['agency' => 9, 'contracts' => 9, 'finance' => 7, 'math' => 7, 'ownership' => 8, 'state_rules' => 10]);

        $question = $attempt->question_ids[0];
        $this->putJson("/practice/attempts/{$attempt->id}/answers/{$question}", ['choice' => 2, 'flagged' => true])->assertOk()
            ->assertJsonPath('data.choice', 2)->assertJsonPath('data.flagged', true)->assertJsonPath('data.feedback', null);

        $this->get("/practice/attempts/{$attempt->id}")->assertInertia(fn (Assert $page) => $page->component('practice/test')->where('attempt.answered', 1)->where('attempt.seconds_left', 2700));
        // Starting again carries on with the same test.
        $this->post('/practice/tests', ['kind' => 'diagnostic']);
        expect(TestAttempt::query()->count())->toBe(1);
    });

    it('scores itself when time runs out, and reports topics weakest first', function () {
        $this->actingAs($this->sofia)->post('/practice/tests', ['kind' => 'diagnostic']);
        $attempt = TestAttempt::query()->sole();
        answerTest($this, $attempt, ['agency', 'contracts', 'ownership', 'state_rules']);

        $this->travel(46)->minutes();
        $this->putJson("/practice/attempts/{$attempt->id}/answers/{$attempt->question_ids[0]}", ['choice' => 0])->assertUnprocessable();
        $this->get("/practice/attempts/{$attempt->id}")->assertRedirect("/practice/attempts/{$attempt->id}/results");

        $this->get("/practice/attempts/{$attempt->id}/results")->assertInertia(fn (Assert $page) => $page
            ->component('practice/results')
            ->where('results.score', 72)
            ->where('results.correct', 36)
            ->where('results.weakest', ['finance', 'math'])
            ->where('results.topics.0.percent', 0)
            ->has('results.review', 50)
            ->where('readiness.percent', 72));
        expect($attempt->refresh()->submitted_at?->equalTo($attempt->ends_at))->toBeTrue();
    });

    it('gives a topic quiz with why after each answer', function () {
        $this->actingAs($this->sofia)->post('/practice/tests', ['kind' => 'topic', 'topic' => 'math']);
        $attempt = TestAttempt::query()->sole();
        $question = QuizQuestion::query()->findOrFail($attempt->question_ids[0]);

        expect($attempt)->mode->toBe('practice')->ends_at->toBeNull()->total->toBe(7);
        $this->putJson("/practice/attempts/{$attempt->id}/answers/{$question->id}", ['choice' => $question->correct_option])
            ->assertJsonPath('data.feedback.correct', true)->assertJsonPath('data.feedback.source', $question->source);
    });

    it('is for candidates only', function () {
        $agent = User::factory()->withRole(Role::Agent)->create();

        $this->actingAs($agent)->get('/practice')->assertNotFound();
        $this->getJson('/api/v1/me/study')->assertNotFound();
    });
});

describe('the study plan and Today, the A8.3 done-when', function () {
    it('starts with the diagnostic, then builds the plan from it, on Today and on the calendar, and Continue opens the first lesson', function () {
        $this->actingAs($this->sofia);

        // Before the diagnostic: day one is the diagnostic.
        $this->getJson('/api/v1/me/study')->assertOk()
            ->assertJsonPath('data.diagnostic_done', false)
            ->assertJsonPath('data.tasks.0.kind', 'diagnostic')
            ->assertJsonPath('data.exam_on', '2027-03-15')
            ->assertJsonPath('data.continue.lesson_id', $this->first->id);

        $this->postJson('/api/v1/practice/tests', ['kind' => 'diagnostic'])->assertCreated();
        $attempt = TestAttempt::query()->sole();
        answerTest($this, $attempt, ['agency', 'contracts', 'ownership', 'state_rules']);
        $this->postJson("/api/v1/practice/attempts/{$attempt->id}/submit")->assertOk()->assertJsonPath('data.weakest', ['finance', 'math']);

        $plan = StudyPlan::query()->where('active', true)->sole();
        expect($plan)->reason->toBe('diagnostic')->weak_topics->toBe(['finance', 'math']);

        // Every day holds exactly 20 minutes: a weak-topic quiz most days, the lessons in order.
        $today = StudyTask::query()->where('study_plan_id', $plan->id)->where('day', '2026-11-23')->orderBy('position')->get();
        expect((int) $today->sum('minutes'))->toBe(20)
            ->and($today->pluck('kind')->all())->toBe(['practice', 'lesson'])
            ->and($today[0]->topic)->toBe('finance')
            ->and($today[1]->lesson_id)->toBe($this->first->id);
        expect(StudyTask::query()->where('study_plan_id', $plan->id)->get()->groupBy(fn ($t) => $t->day->toDateString())->every(fn ($day) => (int) $day->sum('minutes') === 20))->toBeTrue();
        // Every seventh day is a review day.
        expect(StudyTask::query()->where('study_plan_id', $plan->id)->where('day', '2026-11-29')->pluck('kind')->unique()->values()->all())->toBe(['review']);

        // On Today (web screen 4 and the phone) …
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('study.readiness', 72)
            ->where('study.tasks.0.why', 'weak')
            ->where('study.continue.lesson_id', $this->first->id));
        // … and on the calendar: the next 14 days at 7:30 pm, 20 minutes each.
        $blocks = CalendarEvent::query()->where('user_id', $this->sofia->id)->where('kind', 'study')->orderBy('starts_at')->get();
        expect($blocks)->toHaveCount(14)
            ->and($blocks[0]->starts_at->setTimezone(StudyPlans::TIMEZONE)->format('Y-m-d H:i'))->toBe('2026-11-23 19:30')
            ->and((int) $blocks[0]->starts_at->diffInMinutes($blocks[0]->ends_at))->toBe(20);

        // Continue opens the first lesson.
        $this->get("/learn/lessons/{$this->first->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('learn/lesson'));
    });

    it('rebuilds after a missed day, carrying the missed quiz into the next days, without moving the exam', function () {
        $this->actingAs($this->sofia)->getJson('/api/v1/me/study');
        $this->postJson('/api/v1/practice/tests', ['kind' => 'diagnostic']);
        $attempt = TestAttempt::query()->sole();
        answerTest($this, $attempt, ['agency', 'contracts', 'ownership', 'state_rules']);
        $this->postJson("/api/v1/practice/attempts/{$attempt->id}/submit");
        $before = CalendarEvent::query()->where('kind', 'study')->pluck('id')->all();

        $this->travel(2)->days();
        $this->getJson('/api/v1/me/study')->assertOk()->assertJsonPath('data.plan.reason', 'missed')->assertJsonPath('data.exam_on', '2027-03-15');

        $plan = StudyPlan::query()->where('active', true)->sole();
        expect(StudyTask::query()->where('study_plan_id', $plan->id)->where('why', 'catch_up')->orderBy('day')->get()->map(fn ($t) => $t->day->toDateString())->all())
            ->toBe(['2026-11-25', '2026-11-26'])
            ->and((int) StudyTask::query()->where('study_plan_id', $plan->id)->where('day', '2026-11-25')->sum('minutes'))->toBe(20);
        // The calendar blocks were moved in place, not duplicated; past days keep theirs.
        expect(CalendarEvent::query()->where('kind', 'study')->where('starts_at', '>=', today(StudyPlans::TIMEZONE)->utc())->count())->toBe(14)
            ->and(array_intersect($before, CalendarEvent::query()->where('kind', 'study')->pluck('id')->all()))->not->toBeEmpty();
    });

    it('rebuilds when minutes a day change, and lets a task move to another day', function () {
        $this->actingAs($this->sofia)->get('/study-plan')->assertOk()->assertInertia(fn (Assert $page) => $page->component('study-plan')->where('week.from', '2026-11-23')->has('days', 7));

        $this->put('/study-plan/settings', ['exam_month' => '2027-03', 'minutes_per_day' => 45, 'reminder_time' => '07:00'])->assertSessionHasNoErrors();
        $plan = StudyPlan::query()->where('active', true)->sole();
        expect($plan)->minutes_per_day->toBe(45)->reason->toBe('minutes');

        $task = StudyTask::query()->where('study_plan_id', $plan->id)->where('day', '2026-11-24')->firstOrFail();
        $this->put("/study-plan/tasks/{$task->id}", ['day' => '2026-11-26'])->assertSessionHasNoErrors();
        expect($task->refresh()->day->toDateString())->toBe('2026-11-26');
        expect(CalendarEvent::query()->where('kind', 'study')->orderBy('starts_at')->first()?->starts_at->setTimezone(StudyPlans::TIMEZONE)->format('H:i'))->toBe('07:00');
    });
});
