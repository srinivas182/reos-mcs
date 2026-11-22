<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\CourseStatus;
use App\Enums\OrganisationType;
use App\Events\Video\SessionShared;
use App\Features\Flags;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VideoSession;
use App\Services\Content\Courses;
use App\Services\Content\LessonFiles;
use App\Services\Content\Lessons;
use App\Video\VideoSessions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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
    $this->stranger = User::factory()->withRole(Role::Candidate)->create();
    $this->travelTo(CarbonImmutable::parse('2026-11-24 14:58', 'America/Chicago'));

    $this->session = app(VideoSessions::class)->schedule($this->rachel, [
        'kind' => VideoSession::ONE_ON_ONE, 'title' => 'Contract review', 'agenda' => null,
        'starts_at' => CarbonImmutable::parse('2026-11-24 15:00', 'America/Chicago'), 'minutes' => 45, 'guests' => [$this->sofia->id],
    ]);
});

describe('the whiteboard', function () {
    it('saves the drawing and its picture with the session, for the people in it', function () {
        $elements = [['id' => 'rect-1', 'type' => 'rectangle', 'version' => 3, 'x' => 10, 'y' => 20]];
        $png = UploadedFile::fake()->image('whiteboard.png', 800, 600);

        $this->actingAs($this->sofia)->post("/sessions/{$this->session->uuid}/whiteboard", ['elements' => json_encode($elements), 'image' => $png], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('image_url', fn ($url) => str_contains((string) $url, '/whiteboard.png'));

        expect(LessonFiles::disk()->exists("sessions/{$this->session->uuid}/whiteboard.png"))->toBeTrue();
        $this->actingAs($this->rachel)->getJson("/sessions/{$this->session->uuid}/whiteboard")->assertOk()->assertJsonPath('elements.0.id', 'rect-1');

        // The phone gets the picture with the session; strangers get nothing.
        $url = $this->actingAs($this->sofia, 'sanctum')->getJson("/api/v1/sessions/{$this->session->uuid}")->json('whiteboard.image_url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($this->stranger)->getJson("/sessions/{$this->session->uuid}/whiteboard")->assertNotFound();
        $this->actingAs($this->stranger)->post("/sessions/{$this->session->uuid}/whiteboard", ['elements' => '[]'], ['Accept' => 'application/json'])->assertNotFound();
    });

    it('accepts only JSON drawings and PNG pictures', function () {
        $this->actingAs($this->sofia)->postJson("/sessions/{$this->session->uuid}/whiteboard", ['elements' => 'not json'])->assertJsonValidationErrors('elements');
        $this->actingAs($this->sofia)->post("/sessions/{$this->session->uuid}/whiteboard", ['elements' => '[]', 'image' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('image');
    });
});

describe('quick actions', function () {
    it('lets the host share a lesson and practice questions everyone sees at once', function () {
        Event::fake([SessionShared::class]);
        $course = app(Courses::class)->create(['title' => 'Principles of Real Estate II', 'type' => 'test_prep', 'states' => ['TX'], 'hours' => 30], $this->rachel);
        $lesson = app(Lessons::class)->create($course, ['title' => 'Earnest money and the option fee', 'language' => 'en'], $this->rachel);
        app(Courses::class)->setStatus($course, CourseStatus::Live, $this->rachel);
        $options = $this->actingAs($this->rachel)->getJson("/sessions/{$this->session->uuid}/share-options")->assertOk();
        expect($options->json('lessons'))->toContain(['id' => $lesson->id, 'title' => 'Earnest money and the option fee', 'course' => 'Principles of Real Estate II'])
            ->and(collect($options->json('topics'))->pluck('topic'))->toContain('agency');

        $this->actingAs($this->rachel)->postJson("/sessions/{$this->session->uuid}/shares", ['kind' => 'lesson', 'lesson' => $lesson->id])->assertCreated()
            ->assertJsonPath('lesson.title', 'Earnest money and the option fee');
        $this->actingAs($this->rachel, 'sanctum')->postJson("/api/v1/sessions/{$this->session->uuid}/shares", ['kind' => 'questions', 'topic' => 'agency'])->assertCreated()
            ->assertJsonPath('kind', 'questions')
            ->assertJsonPath('questions.0.options', fn ($o) => count($o) === 4);

        Event::assertDispatchedTimes(SessionShared::class, 2);
        $this->actingAs($this->sofia)->get("/sessions/{$this->session->uuid}")->assertInertia(fn (Assert $page) => $page
            ->where('session.shares.0.kind', 'lesson')
            ->where('session.shares.1.topic', 'agency'));
    });

    it('is for the host only, while the session is on', function () {
        $this->actingAs($this->sofia)->getJson("/sessions/{$this->session->uuid}/share-options")->assertJsonValidationErrors('session');
        $this->actingAs($this->sofia, 'sanctum')->postJson("/api/v1/sessions/{$this->session->uuid}/shares", ['kind' => 'questions', 'topic' => 'agency'])->assertJsonValidationErrors('session');
        $this->actingAs($this->rachel)->postJson("/sessions/{$this->session->uuid}/shares", ['kind' => 'questions', 'topic' => 'astrology'])->assertJsonValidationErrors('topic');

        $this->travelTo(CarbonImmutable::parse('2026-11-24 15:46', 'America/Chicago'));
        $this->actingAs($this->rachel)->postJson("/sessions/{$this->session->uuid}/shares", ['kind' => 'questions', 'topic' => 'agency'])->assertJsonValidationErrors('session');
    });
});
