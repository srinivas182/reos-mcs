<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\CourseStatus;
use App\Content\Review\ContentChecks;
use App\Enums\StateStatus;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\IntegrationSetting;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\State;
use App\Models\User;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use Database\Seeders\Demo\DemoContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
});

function reviewCourse(User $ops, string $body, string $type = 'test_prep', CourseStatus $status = CourseStatus::InReview): Course
{
    $course = app(Courses::class)->create(['title' => 'Principles of Real Estate II', 'type' => $type, 'states' => ['TX']], $ops);
    $lesson = app(Lessons::class)->create($course, ['title' => 'Agency disclosure'], $ops);
    app(Lessons::class)->update($lesson, ['body' => $body], $ops);

    if ($status !== CourseStatus::Draft) {
        app(Courses::class)->setStatus($course, $status, $ops);
    }

    return $course->refresh();
}

describe('content and approvals (screen 63)', function () {
    it('lists the review queue with tenant, states, checks and status', function () {
        reviewCourse($this->ops, 'Follow these steps and we guarantee you pass.');
        reviewCourse($this->ops, 'Clean text.', status: CourseStatus::Live);
        app(Courses::class)->create(['title' => 'Contracts for new agents', 'type' => 'ce', 'states' => ['TX']], $this->ops);

        $this->actingAs($this->ops)->get('/admin/content')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/content/index')
            ->where('waiting', 1)
            ->has('items', 3)
            ->where('items.0.status', 'in_review')
            ->where('items.0.flags', 1)
            ->where('items.0.tenant', null)
            ->where('items.0.states', ['Texas'])
            ->where('items.1.missing_approvals', ['TX']));

        $this->get('/admin/content?queue=1')->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('queueOnly', true));
    });

    it('blocks publishing while a legal flag is open, until a reviewer clears it', function () {
        $course = reviewCourse($this->ops, "# Results\nFollow these steps and we guarantee you pass on the first try.");

        $this->actingAs($this->ops)->get("/admin/content/courses/{$course->id}")->assertInertia(fn (Assert $page) => $page
            ->where('checks.open', 1)
            ->where('checks.flags.0.rule', 'guarantee')
            ->where('checks.flags.0.heading', 'Results')
            ->where('checks.flags.0.excerpt', 'Follow these steps and we [[guarantee]] you pass on the first try.'));

        $this->from("/admin/content/courses/{$course->id}")->put("/admin/content/courses/{$course->id}/status", ['status' => 'live'])->assertSessionHasErrors('status');

        $key = $course->lessons()->sole()->passages()->sole()->content_hash.':guarantee';
        $this->post("/admin/content/courses/{$course->id}/flags/{$key}")->assertRedirect();
        $this->put("/admin/content/courses/{$course->id}/status", ['status' => 'live'])->assertSessionHasNoErrors();

        $course->refresh();
        expect($course->status)->toBe(CourseStatus::Live)
            ->and($course->reviewed_by)->toBe($this->ops->id)
            ->and(AuditEvent::query()->where('action', 'course.flag_cleared')->sole()->properties['rule'])->toBe('guarantee');

        // Undo puts the flag back.
        $this->delete("/admin/content/courses/{$course->id}/flags/{$key}")->assertRedirect();
        expect($course->refresh()->cleared_flags)->toBeNull();
    });

    it('flags again when the flagged line changes', function () {
        $course = reviewCourse($this->ops, 'We guarantee you pass.');
        $key = $course->lessons()->sole()->passages()->sole()->content_hash.':guarantee';
        app(Courses::class)->clearFlag($course, $key, $this->ops);

        app(Lessons::class)->update($course->lessons()->sole(), ['body' => 'We guarantee you pass, 100% of the time.'], $this->ops);

        expect(app(ContentChecks::class)->for($course->refresh())['open'])->toBe(1);
    });

    it('allows an approval claim only when the approval is on record', function () {
        $course = reviewCourse($this->ops, 'This is a TREC-approved course.', 'ce', CourseStatus::Draft);
        expect(app(ContentChecks::class)->for($course)['flags'][0]['rule'])->toBe('approval_claim');

        app(Courses::class)->update($course, ['approvals' => ['TX' => ['number' => 'TREC 0451']]], $this->ops);

        expect(app(ContentChecks::class)->for($course->refresh())['clean'])->toBeTrue();
    });

    it('reads Spanish lines too', function () {
        $course = reviewCourse($this->ops, 'Le garantizamos el resultado; el mejor curso de Texas.');

        expect(collect(app(ContentChecks::class)->for($course)['flags'])->pluck('rule')->all())->toBe(['guarantee', 'superlative']);
    });

    it('sends a course back with a note', function () {
        $course = reviewCourse($this->ops, 'Clean text.');

        $this->actingAs($this->ops)->put("/admin/content/courses/{$course->id}/status", ['status' => 'draft', 'note' => 'Add the 2026 IABS form.'])->assertRedirect();

        expect($course->refresh()->status)->toBe(CourseStatus::Draft)->and($course->status_note)->toBe('Add the 2026 IABS form.');
    });
});

describe('learn (A5.3b)', function () {
    beforeEach(function () {
        app(DemoContent::class)->seed();
        $this->candidate = User::factory()->withRole(Role::Candidate)->create(['name' => 'Maria Lopez']);
    });

    it('shows live courses for my state, and search results that open at their place', function () {
        $this->actingAs($this->candidate)->get('/learn?q=IABS')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('learn/index')
            ->has('courses', 2)
            ->where('courses', fn ($courses) => collect($courses)->pluck('title')->sort()->values()->all() === ['Principles of Real Estate II', 'Éxito en el examen: Texas'])
            ->where('search.results', fn ($results) => collect($results)->contains(fn ($r) => $r['seconds'] === 245)));

        $lesson = Lesson::query()->where('title', '6.2 Agency disclosure')->sole();
        $this->get("/learn/lessons/{$lesson->id}?t=245")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('learn/lesson')
            ->where('at.seconds', 245)
            ->where('lesson.sections.0.heading', 'First contact')
            ->where('lesson.transcript.2', ['seconds' => 245, 'text' => 'So before you talk about price or terms, hand them the IABS form.'])
            ->where('lesson.next', fn ($next) => is_int($next)));
    });

    it('never opens a course that is not live, or not for my state', function () {
        $draft = Course::query()->where('slug', 'contracts-for-new-agents')->sole();
        $lesson = app(Lessons::class)->create($draft, ['title' => 'Draft lesson'], $this->ops);

        $this->actingAs($this->candidate)->get("/learn/lessons/{$lesson->id}")->assertNotFound();

        State::query()->whereKey('LA')->update(['status' => StateStatus::Live]);
        $this->candidate->forceFill(['target_state' => 'LA'])->save();
        $texas = Lesson::query()->where('title', '6.2 Agency disclosure')->sole();

        $this->get("/learn/lessons/{$texas->id}")->assertNotFound();
        $this->get('/learn')->assertInertia(fn (Assert $page) => $page->has('courses', 0));
    });

    it('is for people who learn', function () {
        $provider = User::factory()->withRole(Role::Provider)->create();

        $this->actingAs($provider)->get('/learn')->assertForbidden();
    });

    it('gives the phone courses, search and a lesson with its media', function () {
        $this->actingAs($this->candidate);

        $this->getJson('/api/v1/learn/courses')->assertOk()->assertJsonPath('state', 'TX')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/learn/search?q=IABS')->assertOk()->assertJsonPath('data.0.course_title', 'Principles of Real Estate II');
        $this->getJson('/api/v1/learn/search?q=a')->assertUnprocessable();

        $lesson = Lesson::query()->where('title', '6.2 Agency disclosure')->sole();
        LessonMedia::query()->create(['lesson_id' => $lesson->id, 'kind' => 'slides', 'path' => 'platform/x.pdf', 'original_name' => 'slides.pdf', 'mime' => 'application/pdf', 'size' => 10, 'status' => 'ready', 'pages' => 2, 'sections' => [['page' => 1, 'text' => 'Agency'], ['page' => 2, 'text' => 'IABS']]]);

        $this->getJson("/api/v1/learn/lessons/{$lesson->id}")->assertOk()
            ->assertJsonPath('data.documents.0.pages.1', ['page' => 2, 'text' => 'IABS'])
            ->assertJsonPath('data.video', null)
            ->assertJsonPath('data.transcript.2.seconds', 245);
    });

    it('streams Mux video with signed tokens', function () {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        app(CredentialVault::class)->put('mux', CredentialEnvironment::Sandbox, ['access_token_id' => 'id', 'secret_key' => 'secret', 'key_id' => 'k1', 'private_key' => $pem], null);
        IntegrationSetting::query()->create(['integration_id' => 'mux', 'mode' => IntegrationMode::Sandbox]);
        $lesson = Lesson::query()->where('title', '6.2 Agency disclosure')->sole();
        LessonMedia::query()->create(['lesson_id' => $lesson->id, 'kind' => 'video', 'path' => 'platform/v.mp4', 'original_name' => 'v.mp4', 'mime' => 'video/mp4', 'size' => 10, 'status' => 'ready', 'mux_asset_id' => 'a1', 'mux_playback_id' => 'play-1', 'duration_seconds' => 754]);

        $this->actingAs($this->candidate);
        $video = $this->getJson("/api/v1/learn/lessons/{$lesson->id}")->assertOk()->json('data.video');

        expect($video['type'])->toBe('hls')
            ->and($video['url'])->toStartWith('https://stream.mux.com/play-1.m3u8?token=')
            ->and($video['poster'])->toStartWith('https://image.mux.com/play-1/thumbnail.webp?token=');
    });
});
