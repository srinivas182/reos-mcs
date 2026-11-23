<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\Builder\LessonReview;
use App\Content\Builder\ModuleSources;
use App\Content\CourseStatus;
use App\Content\MediaStatus;
use App\Enums\OrganisationType;
use App\Jobs\ProcessModuleSource;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\ModuleSource;
use App\Models\Organisation;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Notifications\SeatTimeChangeAsked;
use App\Services\Content\LessonFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
    $this->rachel = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Dr. Rachel Hughes']);
    $this->marcus = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Marcus Lee']);
    $this->course = Course::query()->create(['organisation_id' => $this->school->id, 'title' => 'Principles of Real Estate II', 'slug' => 'principles-ii', 'type' => 'qualifying', 'states' => ['TX'], 'hours' => 30]);
    $this->course->instructors()->attach($this->rachel->id);
});

/** Uploads a source file the way the browser does: begin, put, complete. */
function uploadSource(object $test, CourseModule $module, string $role, string $name, string $content): ModuleSource
{
    $begun = $test->postJson("/course-builder/modules/{$module->id}/sources", ['role' => $role, 'name' => $name, 'size' => strlen($content)])->assertCreated();
    $source = ModuleSource::query()->findOrFail($begun->json('id'));
    LessonFiles::disk()->put($source->path, $content);
    $test->postJson("/course-builder/sources/{$source->id}/complete")->assertOk();

    return $source->refresh();
}

describe('who builds what', function () {
    it('shows instructors only the courses they teach, and school admins all of them', function () {
        Course::query()->create(['organisation_id' => $this->school->id, 'title' => 'Contracts for new agents', 'slug' => 'contracts', 'type' => 'ce', 'states' => ['TX']]);

        $this->actingAs($this->rachel)->get('/course-builder')->assertInertia(fn (Assert $page) => $page->component('builder/index')->has('courses', 1));
        $this->actingAs($this->marcus)->get('/course-builder')->assertInertia(fn (Assert $page) => $page->has('courses', 0));
        $this->actingAs($this->marcus)->get("/course-builder/{$this->course->id}")->assertNotFound();
        $this->actingAs($this->paul)->get('/course-builder')->assertInertia(fn (Assert $page) => $page->has('courses', 2));

        $other = Organisation::factory()->ofType(OrganisationType::School)->create();
        $outsider = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $other)->create();
        $this->actingAs($outsider)->get("/course-builder/{$this->course->id}")->assertNotFound();
        $agent = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($agent)->get('/course-builder')->assertForbidden();
    });

    it('lets an instructor create a course the school owns, and teach it', function () {
        $this->actingAs($this->marcus)->post('/course-builder', ['title' => 'Legal Update I', 'type' => 'ce', 'delivery' => 'ai_avatar', 'hours' => 4])->assertRedirect();
        $course = Course::query()->where('title', 'Legal Update I')->sole();

        expect($course->organisation_id)->toBe($this->school->id)
            ->and($course->delivery->value)->toBe('ai_avatar')
            ->and($course->instructors()->pluck('users.id')->all())->toBe([$this->marcus->id]);
    });
});

describe('building a module from source files (the A7.2 done-when)', function () {
    it('uploads, checks and reads the material, plans the AI work, and arranges lessons', function () {
        Queue::fake();
        $this->actingAs($this->rachel)->post("/course-builder/{$this->course->id}/modules", ['title' => 'Agency in Texas'])->assertRedirect();
        $module = CourseModule::query()->sole();

        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:06.000\nEvery license holder must explain who they represent.\n\n00:00:07.000 --> 00:00:12.000\nGive the IABS form at the first substantive contact.\n";
        $source = uploadSource($this, $module, 'captions', 'class-2027-02-10.vtt', $vtt);
        expect($source->status)->toBe(MediaStatus::Processing)
            ->and($source->path)->toStartWith("tenants/{$this->school->id}/courses/{$this->course->id}/sources/");
        Queue::assertPushed(ProcessModuleSource::class);

        app(ModuleSources::class)->process($source);
        $source->refresh();
        expect($source->status)->toBe(MediaStatus::Ready)
            ->and($source->words)->toBeGreaterThan(10)
            ->and(app(ModuleSources::class)->passages($module)[0]['text'])->toContain('IABS form');

        // Wrong file for its kind is refused, and never processed.
        $fake = uploadSource($this, $module, 'slides', 'deck.pptx', 'not a zip file at all');
        expect($fake->status)->toBe(MediaStatus::Rejected);

        $this->get("/course-builder/{$this->course->id}?module={$module->id}")->assertInertia(fn (Assert $page) => $page
            ->component('builder/course')
            ->where('module.sources.0.status', 'ready')
            ->where('module.plan.sources_ready', 1)
            ->where('module.plan.minutes', 1)
            ->where('module.plan.lessons', 1)
            // One minute, in English and Spanish, at 10 credits a minute.
            ->where('module.plan.credits', 20)
            ->where('modules.0.status', 'sources'));

        $this->post("/course-builder/modules/{$module->id}/lessons", ['title' => 'Who you represent'])->assertRedirect();
        $this->post("/course-builder/modules/{$module->id}/lessons", ['title' => 'The IABS form'])->assertRedirect();
        [$first, $second] = Lesson::query()->orderBy('position')->get()->all();
        expect($first->review_status)->toBe(LessonReview::Draft);

        $this->post("/course-builder/lessons/{$second->id}/move", ['direction' => 'up'])->assertRedirect();
        expect(Lesson::query()->orderBy('position')->pluck('title')->all())->toBe(['The IABS form', 'Who you represent']);
        expect(AuditEvent::query()->where('action', 'module.source_added')->pluck('organisation_id')->unique()->all())->toBe([$this->school->id]);
    });

    it('keeps lessons from learners until approved, and an edit sends them back to draft', function () {
        $module = CourseModule::query()->create(['course_id' => $this->course->id, 'title' => 'Agency', 'position' => 1]);
        $this->course->approvals()->create(['state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'TREC 0451', 'status' => 'approved']);
        $this->course->forceFill(['status' => CourseStatus::Live])->save();
        $this->actingAs($this->rachel)->post("/course-builder/modules/{$module->id}/lessons", ['title' => 'Who you represent']);
        $lesson = Lesson::query()->sole();
        $candidate = User::factory()->withRole(Role::Candidate)->create(['target_state' => 'TX']);
        $lessons = fn () => collect($this->actingAs($candidate)->getJson('/api/v1/learn/courses')->json('data'))->firstWhere('id', $this->course->id)['lessons'] ?? [];

        $this->actingAs($this->rachel)->post("/course-builder/lessons/{$lesson->id}/approve")->assertSessionHasErrors('lesson');
        expect($lessons())->toBe([]);

        $this->actingAs($this->rachel)->patch("/course-builder/lessons/{$lesson->id}", ['body' => "# Agency\nExplain who you represent."])->assertRedirect();
        $this->post("/course-builder/lessons/{$lesson->id}/approve")->assertSessionHasNoErrors();
        expect($lesson->refresh()->review_status)->toBe(LessonReview::Approved)
            ->and(collect($lessons())->pluck('title')->all())->toBe(['Who you represent']);

        $this->actingAs($this->rachel)->patch("/course-builder/lessons/{$lesson->id}", ['body' => 'Changed.'])->assertRedirect();
        expect($lesson->refresh()->review_status)->toBe(LessonReview::Draft)
            ->and($lessons())->toBe([]);
    });
});

describe('settings and seat time', function () {
    it('offers only instructors with avatar consent as presenters', function () {
        $this->actingAs($this->rachel)->put("/course-builder/{$this->course->id}/settings", ['language' => 'both', 'lesson_minutes' => 8, 'presenter' => "instructor:{$this->rachel->id}"])
            ->assertSessionHasErrors('presenter');
        $this->put("/course-builder/{$this->course->id}/settings", ['language' => 'both', 'lesson_minutes' => 8, 'presenter' => 'stock'])->assertSessionHasNoErrors();

        expect($this->course->refresh()->builder)->toBe(['language' => 'both', 'lesson_minutes' => 8, 'presenter' => 'stock']);
    });

    it('sends seat-time changes on an approved course to the school instead of changing them', function () {
        Notification::fake();
        $this->course->approvals()->create(['state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'TREC 0451', 'status' => 'approved']);

        $this->actingAs($this->rachel)->get("/course-builder/{$this->course->id}")->assertInertia(fn (Assert $page) => $page->where('course.hours_locked', true)->where('course.can_publish', false));
        $this->post("/course-builder/{$this->course->id}/seat-time", ['hours' => 32, 'reason' => 'Two hours of new 2027 contract changes.'])->assertSessionHasNoErrors();

        expect((float) $this->course->refresh()->hours)->toBe(30.0);
        Notification::assertSentTo($this->paul, SeatTimeChangeAsked::class, fn (SeatTimeChangeAsked $n) => $n->hours === 32.0);
    });

    it('lets only the school admin publish, with the course checks', function () {
        $this->actingAs($this->rachel)->put("/course-builder/{$this->course->id}/status", ['status' => 'live'])->assertForbidden();
        $this->actingAs($this->paul)->put("/course-builder/{$this->course->id}/status", ['status' => 'live'])->assertSessionHasErrors('status');

        $this->course->approvals()->create(['state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'TREC 0451', 'status' => 'approved']);
        $this->put("/course-builder/{$this->course->id}/status", ['status' => 'live'])->assertSessionHasNoErrors();
        expect($this->course->refresh()->status)->toBe(CourseStatus::Live);
    });
});

describe('the instructor dashboard (screen 42)', function () {
    it('shows reviews, drafts, sessions and the most-missed topics', function () {
        $module = CourseModule::query()->create(['course_id' => $this->course->id, 'title' => 'Agency', 'position' => 1]);
        Lesson::query()->create(['course_id' => $this->course->id, 'course_module_id' => $module->id, 'title' => 'Draft', 'position' => 1, 'review_status' => 'draft']);
        $candidate = User::factory()->withRole(Role::Candidate, $this->school)->create();
        $agency = QuizQuestion::query()->create(['state_code' => 'TX', 'topic' => 'agency', 'content' => ['question' => 'Q', 'options' => ['a', 'b']], 'correct_option' => 0, 'source' => 'TREC']);
        QuizAttempt::query()->create(['user_id' => $candidate->id, 'kind' => 'practice', 'state_code' => 'TX', 'answers' => [['question_id' => $agency->id, 'choice' => 1, 'correct' => false]], 'correct' => 0, 'total' => 1, 'completed_at' => now()]);

        $this->actingAs($this->rachel)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('teaching.courses', 1)
            ->where('teaching.drafts', 1)
            ->where('teaching.pass_rate', 0)
            ->where('teaching.missed.0', ['topic' => 'agency', 'missed' => 1, 'answered' => 1]));
    });
});
