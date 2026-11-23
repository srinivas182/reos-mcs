<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\Builder\LessonReview;
use App\Content\CourseStatus;
use App\Content\CourseType;
use App\Content\ExamSuccess;
use App\Content\Review\ContentChecks;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonVersion;
use App\Models\Organisation;
use App\Models\ReviewItem;
use App\Models\TestAttempt;
use App\Models\User;
use App\Practice\StudyPlans;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config()->string('reos.content.disk'));
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', StudyPlans::TIMEZONE));
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->nelle = User::factory()->withTwoFactor()->withRole(Role::Instructor, $this->school)->create(['name' => 'Nelle Brooks', 'email' => 'nelle@demo.reos.test']);
});

describe('the Exam success course (screen 23, A9.2f)', function () {
    it('has eight scripts in English and Spanish that pass the legal-line checks, two questions each', function () {
        $scripts = ExamSuccess::scripts();

        expect($scripts)->toHaveCount(8)
            ->and(array_column(array_column($scripts, 'title'), 'en'))->toBe(['How the exam is built', 'Timing and pacing', 'Reading NOT, EXCEPT and BEST questions', 'Eliminating wrong answers', 'Math shortcuts', 'Texas portion traps', 'Your last 7 days', 'Exam day and nerves']);

        foreach ($scripts as $script) {
            expect($script['questions'])->toHaveCount(2);
            foreach ($script['sections'] as $section) {
                foreach ($section['lines'] as $line) {
                    expect(trim($line['en']))->not->toBe('')->and(trim($line['es']))->not->toBe('')
                        ->and(ContentChecks::rulesBrokenBy($line['en']))->toBe([])->and(ContentChecks::rulesBrokenBy($line['es']))->toBe([]);
                }
            }
            foreach ($script['questions'] as $question) {
                expect($question['en']['options'])->toHaveCount(4)->and($question['es']['options'])->toHaveCount(4)
                    ->and($question['correct'])->toBeBetween(0, 3);
            }
        }
    });

    it('installs REOS\'s course as lessons waiting for review on screen 44, once', function () {
        $this->artisan('reos:content:exam-success', ['--reviewer' => ['nelle@demo.reos.test']])->assertSuccessful();
        $this->artisan('reos:content:exam-success', ['--reviewer' => ['nelle@demo.reos.test']])->assertSuccessful();

        $course = ExamSuccess::course();
        expect($course)->not->toBeNull()
            ->and($course?->organisation_id)->toBeNull()
            ->and($course?->type)->toBe(CourseType::TestPrep)
            ->and($course?->status)->toBe(CourseStatus::Draft)
            ->and($course?->lessons()->count())->toBe(8)
            ->and($course?->instructors()->pluck('users.id')->all())->toBe([$this->nelle->id]);

        $lesson = Lesson::query()->where('course_id', $course?->id)->orderBy('position')->firstOrFail();
        $version = LessonVersion::query()->where('lesson_id', $lesson->id)->sole();
        expect($version->lines)->not->toBeEmpty()
            ->and($version->lines[0])->toMatchArray(['en' => 'The Texas sales agent exam has two portions: a national portion about real estate everywhere, and a Texas portion about Texas law and TREC rules.', 'source' => '1. How the exam is built'])
            ->and($version->lines[0]['es'])->toStartWith('El examen de agente de ventas de Texas')
            ->and($version->flags)->toBe([])
            ->and($version->review_state)->toBe('in_review')
            ->and(ReviewItem::query()->where('lesson_version_id', $version->id)->where('kind', 'question')->count())->toBe(2);

        // Videos come back from the (simulated) vendor; then each lesson waits for Nelle.
        $this->artisan('reos:lessons:renders');
        expect($lesson->refresh()->review_status)->toBe(LessonReview::InReview);

        $this->actingAs($this->nelle)->get('/ai-lessons')->assertOk()->assertInertia(fn (Assert $page) => $page->has('lessons', 8));
        $this->get("/course-builder/lessons/{$lesson->id}/review")->assertOk()->assertInertia(fn (Assert $page) => $page->component('builder/review')->where('lesson.course.title', 'Exam success: Texas'));

        $other = User::factory()->withTwoFactor()->withRole(Role::Instructor, $this->school)->create();
        $this->actingAs($other)->get("/course-builder/lessons/{$lesson->id}/review")->assertNotFound();
    });

    it('needs every reviewer to exist', function () {
        $this->artisan('reos:content:exam-success', ['--reviewer' => ['nobody@demo.reos.test']])->assertFailed();
        expect(ExamSuccess::course())->toBeNull();
    });

    it('is suggested on the practice hub once readiness passes 60%, at the next lesson not finished', function () {
        $course = app(ExamSuccess::class)->install([$this->nelle]);
        $lessons = Lesson::query()->where('course_id', $course->id)->orderBy('position')->get();
        $lessons->each(fn (Lesson $l) => $l->forceFill(['review_status' => LessonReview::Approved])->save());
        $course->forceFill(['status' => CourseStatus::Live])->save();

        $sofia = User::factory()->withRole(Role::Candidate, $this->school)->create(['name' => 'Sofia Reyes', 'onboarding_path' => OnboardingPath::BecomeAgent->value, 'exam_month' => '2027-03-01', 'onboarded_at' => now()]);
        $diagnostic = fn (int $right, int $total) => TestAttempt::query()->create(['user_id' => $sofia->id, 'kind' => 'diagnostic', 'mode' => 'exam', 'state_code' => 'TX', 'question_ids' => [], 'total' => 0,
            'started_at' => now()->subDay(), 'submitted_at' => now()->subDay(),
            'by_topic' => array_fill_keys(['agency', 'contracts', 'ownership', 'finance', 'math', 'state_rules'], ['correct' => $right, 'total' => $total])]);

        $diagnostic(8, 20);
        $low = $this->actingAs($sofia)->getJson('/api/v1/practice/hub')->json('data.ranked');
        expect(array_column($low, 'mode'))->not->toContain('exam_success');

        TestAttempt::query()->where('user_id', $sofia->id)->delete();
        $diagnostic(38, 40);
        LessonProgress::query()->create(['user_id' => $sofia->id, 'lesson_id' => $lessons[0]->id, 'counted_seconds' => 60, 'required_seconds' => 60, 'completed_at' => now()]);
        $ranked = collect($this->getJson('/api/v1/practice/hub')->json('data.ranked'));
        expect($ranked->firstWhere('mode', 'exam_success'))->toMatchArray(['why' => 'ready_soon', 'count' => 7, 'lesson_id' => $lessons[1]->id])
            ->and($ranked->every(fn (array $s): bool => array_key_exists('lesson_id', $s)))->toBeTrue();
    });
});
