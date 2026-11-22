<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\CourseStatus;
use App\Content\IngestStatus;
use App\Content\Knowledge\Embedders;
use App\Content\Knowledge\KnowledgeSearch;
use App\Content\MediaStatus;
use App\Content\Scanning\FileScanner;
use App\Content\Scanning\ScanResult;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\IntegrationSetting;
use App\Models\KbPassage;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\User;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use Aws\MockHandler;
use Aws\Result;
use Database\Seeders\Demo\DemoContent;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
});

/** A course with one lesson, as Ops would make it. */
function course(User $ops, array $data = [], ?CourseStatus $status = CourseStatus::Live): Course
{
    $course = app(Courses::class)->create([
        'title' => $data['title'] ?? 'Principles of Real Estate II',
        'type' => $data['type'] ?? 'test_prep',
        'states' => $data['states'] ?? ['TX'],
        'hours' => 30,
    ], $ops);

    if ($status !== null && $status !== CourseStatus::Draft) {
        app(Courses::class)->setStatus($course, $status, $ops);
    }

    return $course;
}

function lesson(User $ops, Course $course, string $body = '', string $language = 'en'): Lesson
{
    $lesson = app(Lessons::class)->create($course, ['title' => 'Agency disclosure', 'language' => $language], $ops);

    if ($body !== '') {
        app(Lessons::class)->update($lesson, ['body' => $body], $ops);
    }

    return $lesson->refresh();
}

/** Uploads a file the way the browser does: ask for a link, put the bytes, say it's done. */
function upload(object $test, User $ops, Lesson $lesson, string $kind, string $name, string $bytes, ?int $declared = null): array
{
    $started = $test->actingAs($ops)->postJson("/admin/content/lessons/{$lesson->id}/files", [
        'kind' => $kind, 'name' => $name, 'size' => $declared ?? strlen($bytes),
    ])->assertCreated()->json();

    expect($started['upload']['method'])->toBe('PUT')
        ->and($started['upload']['headers']['Content-Type'])->not->toBeEmpty();

    $media = LessonMedia::query()->findOrFail($started['id']);
    Storage::disk('local')->put($media->path, $bytes);

    return $test->postJson("/admin/content/files/{$media->id}/complete")->assertOk()->json();
}

describe('who can manage content', function () {
    it('is the super admin, with two-step', function () {
        $this->actingAs($this->ops)->get('/admin/content/library')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/content/library')->has('courses', 0)->where('search.results', null));
    });

    it('is nobody else', function () {
        $agent = User::factory()->withRole(Role::Agent)->create();

        $this->actingAs($agent)->get('/admin/content/library')->assertForbidden();
        $this->actingAs($agent)->post('/admin/content/courses', ['title' => 'Mine', 'type' => 'ce'])->assertForbidden();
    });
});

describe('courses (A5.3)', function () {
    it('creates a course and opens it', function () {
        $this->actingAs($this->ops)->post('/admin/content/courses', [
            'title' => 'Legal Update I', 'type' => 'ce', 'states' => ['tx'], 'hours' => 4,
        ])->assertRedirect();

        $course = Course::query()->sole();
        expect($course->slug)->toBe('legal-update-i')
            ->and($course->states)->toBe(['TX'])
            ->and($course->status)->toBe(CourseStatus::Draft);

        $this->get("/admin/content/courses/{$course->id}")->assertInertia(fn (Assert $page) => $page
            ->component('admin/content/course')
            ->where('course.needs_approval', true)
            ->where('course.missing_approvals', ['TX']));
        expect(AuditEvent::query()->where('action', 'course.created')->where('actor_id', $this->ops->id)->exists())->toBeTrue();
    });

    it('keeps a CE course hidden until it has the approval number for each state', function () {
        $course = course($this->ops, ['title' => 'Legal Update I', 'type' => 'ce'], CourseStatus::Draft);

        $this->actingAs($this->ops)->from("/admin/content/courses/{$course->id}")
            ->put("/admin/content/courses/{$course->id}/status", ['status' => 'live'])
            ->assertSessionHasErrors('status');
        expect($course->refresh()->status)->toBe(CourseStatus::Draft);

        $this->patch("/admin/content/courses/{$course->id}", ['approvals' => ['TX' => ['number' => 'TREC 40123', 'expires_on' => '2028-08-31'], 'LA' => ['number' => 'ignored']]])->assertRedirect();
        $this->put("/admin/content/courses/{$course->id}/status", ['status' => 'live'])->assertSessionHasNoErrors();

        expect($course->refresh()->status)->toBe(CourseStatus::Live)
            ->and($course->approvals)->toBe(['TX' => ['number' => 'TREC 40123', 'expires_on' => '2028-08-31']])
            ->and($course->published_at)->not->toBeNull();

        $audit = AuditEvent::query()->where('action', 'course.status_changed')->sole();
        expect($audit->properties)->toBe(['from' => 'draft', 'to' => 'live']);

        // Removing the number from a live course is refused.
        $this->patch("/admin/content/courses/{$course->id}", ['approvals' => []])->assertSessionHasErrors('approvals');
    });

    it('deletes a course at once, and Undo brings it back', function () {
        $course = course($this->ops);

        $this->actingAs($this->ops)->delete("/admin/content/courses/{$course->id}")->assertRedirect('/admin/content/library');
        expect(Course::query()->count())->toBe(0);

        $this->post("/admin/content/courses/{$course->id}/restore")->assertRedirect();
        expect(Course::query()->count())->toBe(1);
    });

    it('moves a module\'s lessons out of it when the module goes', function () {
        $course = course($this->ops);
        $this->actingAs($this->ops)->post("/admin/content/courses/{$course->id}/modules", ['title' => 'Unit 6'])->assertRedirect();
        $module = $course->modules()->sole();
        $this->post("/admin/content/courses/{$course->id}/lessons", ['title' => 'Agency disclosure', 'course_module_id' => $module->id])->assertRedirect();
        $this->patch("/admin/content/modules/{$module->id}", ['title' => 'Unit 6: Agency'])->assertRedirect();

        $this->delete("/admin/content/modules/{$module->id}")->assertRedirect();

        expect(Lesson::query()->sole()->course_module_id)->toBeNull();
    });

    it('refuses a module from another course', function () {
        $course = course($this->ops);
        $other = course($this->ops, ['title' => 'Other']);
        $module = $other->modules()->create(['title' => 'Elsewhere']);

        $this->actingAs($this->ops)->post("/admin/content/courses/{$course->id}/lessons", ['title' => 'X', 'course_module_id' => $module->id])
            ->assertSessionHasErrors('course_module_id');
    });
});

describe('the knowledge base (A5.3)', function () {
    it('makes lesson text searchable, opening at its heading', function () {
        $lesson = lesson($this->ops, course($this->ops), "# First contact\nGive the Information About Brokerage Services form at the first substantive contact.");

        expect($lesson->ingest_status)->toBe(IngestStatus::Ready)
            ->and($lesson->passages()->sole()->heading)->toBe('First contact');

        $this->actingAs($this->ops)->get('/admin/content/library?q=brokerage%20services&state=TX')
            ->assertInertia(fn (Assert $page) => $page
                ->has('search.results', 1)
                ->where('search.results.0.lesson_id', $lesson->id)
                ->where('search.results.0.heading', 'First contact')
                ->where('search.results.0.match', 'keyword'));
    });

    it('finds only live courses in the learner\'s state and language', function () {
        $texas = course($this->ops, ['title' => 'Texas prep']);
        lesson($this->ops, $texas, 'The IABS notice in Texas.');
        $draft = course($this->ops, ['title' => 'Draft prep'], CourseStatus::Draft);
        lesson($this->ops, $draft, 'The IABS notice, draft.');
        $louisiana = course($this->ops, ['title' => 'Louisiana prep', 'states' => ['LA']]);
        lesson($this->ops, $louisiana, 'The IABS notice in Louisiana.');
        $everywhere = course($this->ops, ['title' => 'Everywhere', 'states' => []]);
        lesson($this->ops, $everywhere, 'El aviso IABS en español.', 'es');

        $titles = fn (array $hits) => collect($hits)->map(fn ($h) => $h->courseTitle)->sort()->values()->all();
        $search = app(KnowledgeSearch::class);

        expect($titles($search->search('IABS', ['TX'])))->toBe(['Everywhere', 'Texas prep'])
            ->and($titles($search->search('IABS', ['TX'], 'en')))->toBe(['Texas prep'])
            ->and($titles($search->search('IABS', ['LA'])))->toBe(['Everywhere', 'Louisiana prep'])
            ->and($titles($search->search('IABS', null, liveOnly: false)))->toHaveCount(4);
    });

    it('follows the course\'s states when they change', function () {
        $course = course($this->ops);
        lesson($this->ops, $course, 'The IABS notice.');

        app(Courses::class)->update($course, ['states' => ['LA']], $this->ops);

        expect(KbPassage::query()->sole()->states)->toBe(['LA'])
            ->and(app(KnowledgeSearch::class)->search('IABS', ['TX']))->toBe([]);
    });

    it('drops a deleted lesson from search, and brings it back on Undo', function () {
        $lesson = lesson($this->ops, course($this->ops), 'The IABS notice.');

        $this->actingAs($this->ops)->delete("/admin/content/lessons/{$lesson->id}")->assertRedirect();
        expect(app(KnowledgeSearch::class)->search('IABS', ['TX']))->toBe([]);

        $this->post("/admin/content/lessons/{$lesson->id}/restore");
        expect(app(KnowledgeSearch::class)->search('IABS', ['TX']))->toHaveCount(1);
    });

    it('re-reads only what changed, keeping the rest', function () {
        $lesson = lesson($this->ops, course($this->ops), "First paragraph stays.\n\n# Next\nSecond part.");
        $kept = $lesson->passages()->where('content', 'First paragraph stays.')->sole();

        app(Lessons::class)->update($lesson, ['body' => "First paragraph stays.\n\n# Next\nSecond part, edited."], $this->ops);

        expect($lesson->passages()->pluck('id')->all())->toContain($kept->id)->toHaveCount(2);
    });
});

describe('lesson files (A5.3)', function () {
    it('done when: a lesson uploaded with a video and slides is searchable, opening at the page or moment', function () {
        $lesson = lesson($this->ops, course($this->ops));

        // Simulator (the default outside production): the video is ready at once.
        $video = upload($this, $this->ops, $lesson, 'video', 'agency-disclosure.mp4', "\x00\x00\x00\x18ftypmp42".str_repeat("\x00", 64));
        $slides = upload($this, $this->ops, $lesson, 'slides', 'Agency slides.pdf', (string) file_get_contents(base_path('tests/Fixtures/content/agency-slides.pdf')));
        upload($this, $this->ops, $lesson, 'captions', 'agency-disclosure.vtt', (string) file_get_contents(base_path('tests/Fixtures/content/lesson.vtt')));

        expect(LessonMedia::query()->pluck('status')->map->value->all())->toBe(['ready', 'ready', 'ready'])
            ->and(LessonMedia::query()->findOrFail($slides['id'])->pages)->toBe(3)
            ->and(LessonMedia::query()->findOrFail($video['id'])->mux_asset_id)->toStartWith('sim-asset-');

        $this->get('/admin/content/library?q=IABS&state=TX')->assertInertia(fn (Assert $page) => $page
            ->has('search.results', 2)
            ->where('search.results', fn ($results) => collect($results)->contains(fn ($r) => $r['kind'] === 'slide' && $r['page'] === 2)
                && collect($results)->contains(fn ($r) => $r['kind'] === 'transcript' && $r['seconds'] === 245)));

        $this->get("/admin/content/lessons/{$lesson->id}")->assertInertia(fn (Assert $page) => $page
            ->component('admin/content/lesson')
            ->has('files', 3)
            ->where('knowledge.video_host', 'simulator')
            ->where('knowledge.meaning', false)
            ->where('lesson.ingest_status', 'ready'));

        expect(AuditEvent::query()->where('action', 'lesson.file_added')->count())->toBe(3);
    });

    it('refuses a file that is not what its name says', function () {
        $lesson = lesson($this->ops, course($this->ops));

        $answer = upload($this, $this->ops, $lesson, 'slides', 'slides.pdf', "MZ\x90\x00 a program");

        expect($answer['status'])->toBe('rejected')
            ->and($answer['failure'])->toBe('content.files.failed.type');
        Storage::disk('local')->assertMissing(LessonMedia::query()->sole()->path);
    });

    it('refuses a file of a different size than declared', function () {
        $lesson = lesson($this->ops, course($this->ops));

        $answer = upload($this, $this->ops, $lesson, 'captions', 'a.vtt', "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi", 9999);

        expect($answer['failure'])->toBe('content.files.failed.size');
    });

    it('refuses the wrong type or a file too big before any upload', function () {
        $lesson = lesson($this->ops, course($this->ops));

        $this->actingAs($this->ops)->postJson("/admin/content/lessons/{$lesson->id}/files", ['kind' => 'slides', 'name' => 'run.exe', 'size' => 10])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson("/admin/content/lessons/{$lesson->id}/files", ['kind' => 'captions', 'name' => 'a.vtt', 'size' => 50 * 1024 * 1024])->assertJsonValidationErrors('size');
        $this->postJson("/admin/content/lessons/{$lesson->id}/files", ['kind' => 'slides', 'name' => '../../a.pdf', 'size' => 10])->assertJsonValidationErrors('name');
    });

    it('deletes a file the virus scanner flags', function () {
        app()->bind(FileScanner::class, fn () => new class implements FileScanner
        {
            public function scan($stream): ScanResult
            {
                return ScanResult::Infected;
            }
        });
        $lesson = lesson($this->ops, course($this->ops));

        upload($this, $this->ops, $lesson, 'slides', 'slides.pdf', '%PDF-1.4 X5O!P%@AP');

        $media = LessonMedia::query()->sole();
        expect($media->status)->toBe(MediaStatus::Rejected)->and($media->failure)->toBe('content.files.failed.infected');
        Storage::disk('local')->assertMissing($media->path);
    });

    it('waits for the scanner when it can\'t be reached', function () {
        app()->bind(FileScanner::class, fn () => new class implements FileScanner
        {
            public function scan($stream): ScanResult
            {
                return ScanResult::Unavailable;
            }
        });
        $lesson = lesson($this->ops, course($this->ops));

        upload($this, $this->ops, $lesson, 'slides', 'slides.pdf', '%PDF-1.4');

        // Released to try again later: still checking, nothing read.
        expect(LessonMedia::query()->sole()->status)->toBe(MediaStatus::Processing)
            ->and(LessonMedia::query()->sole()->scanned_at)->toBeNull();
    });

    it('removes a file and its passages', function () {
        $lesson = lesson($this->ops, course($this->ops));
        upload($this, $this->ops, $lesson, 'captions', 'a.vtt', (string) file_get_contents(base_path('tests/Fixtures/content/lesson.vtt')));
        $media = LessonMedia::query()->sole();
        expect($lesson->passages()->count())->toBeGreaterThan(0);

        $this->delete("/admin/content/files/{$media->id}")->assertRedirect();

        expect(LessonMedia::query()->count())->toBe(0)->and($lesson->passages()->count())->toBe(0);
        Storage::disk('local')->assertMissing($media->path);
    });
});

describe('Mux (A5.3)', function () {
    beforeEach(function () {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        app(CredentialVault::class)->put('mux', CredentialEnvironment::Sandbox, [
            'access_token_id' => 'token-id', 'secret_key' => 'token-secret', 'webhook_secret' => 'whsec-test',
            'key_id' => 'signing-key-1', 'private_key' => base64_encode($pem),
        ], null);
        IntegrationSetting::query()->create(['integration_id' => 'mux', 'mode' => IntegrationMode::Sandbox]);
    });

    function muxEvent(object $test, array $event): TestResponse
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);
        $time = time();

        return $test->call('POST', '/webhooks/mux', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_MUX_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', $time.'.'.$body, 'whsec-test'),
        ], $body);
    }

    it('sends the video to Mux with signed playback and captions, then makes what is said searchable', function () {
        Http::fake([
            'api.mux.com/video/v1/assets' => Http::response(['data' => ['id' => 'asset-1', 'playback_ids' => [['id' => 'play-1', 'policy' => 'signed']]]], 201),
            'stream.mux.com/*' => Http::response((string) file_get_contents(base_path('tests/Fixtures/content/lesson.vtt'))),
        ]);
        $lesson = lesson($this->ops, course($this->ops));

        upload($this, $this->ops, $lesson, 'video', 'agency.mp4', "\x00\x00\x00\x18ftypmp42");

        $media = LessonMedia::query()->sole();
        expect($media->status)->toBe(MediaStatus::Processing)->and($media->mux_asset_id)->toBe('asset-1');
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.mux.com/video/v1/assets'
            && $request['playback_policies'] === ['signed']
            && $request['inputs'][0]['generated_subtitles'][0]['language_code'] === 'en'
            && str_contains($request['inputs'][0]['url'], 'platform/lessons/'.$lesson->id)
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('token-id:token-secret')));

        muxEvent($this, ['id' => 'evt-1', 'type' => 'video.asset.ready', 'data' => ['id' => 'asset-1', 'duration' => 754.2, 'playback_ids' => [['id' => 'play-1']]]])->assertOk();
        expect($media->refresh()->status)->toBe(MediaStatus::Ready)->and($media->duration_seconds)->toBe(754);

        muxEvent($this, ['id' => 'evt-2', 'type' => 'video.asset.track.ready', 'data' => ['id' => 'track-9', 'asset_id' => 'asset-1', 'type' => 'text', 'text_source' => 'generated_vod']])->assertOk();

        Http::assertSent(function (HttpRequest $request) {
            if (! str_starts_with($request->url(), 'https://stream.mux.com/play-1/text/track-9.vtt')) {
                return false;
            }
            $claims = JWT::decode($request['token'], new Key($this->publicKey, 'RS256'));

            return $claims->sub === 'play-1' && $claims->aud === 'v';
        });
        expect(app(KnowledgeSearch::class)->search('IABS', ['TX'])[0]->seconds)->toBe(245);
    });

    it('handles each event once and refuses unsigned ones', function () {
        Http::fake(['api.mux.com/*' => Http::response(['data' => ['id' => 'asset-1', 'playback_ids' => []]], 201)]);
        $lesson = lesson($this->ops, course($this->ops));
        upload($this, $this->ops, $lesson, 'video', 'agency.mp4', "\x00\x00\x00\x18ftypmp42");

        $this->postJson('/webhooks/mux', ['id' => 'evt-x', 'type' => 'video.asset.ready', 'data' => ['id' => 'asset-1']])->assertUnauthorized();
        muxEvent($this, ['id' => 'evt-3', 'type' => 'video.asset.errored', 'data' => ['id' => 'asset-1']])->assertOk();
        muxEvent($this, ['id' => 'evt-3', 'type' => 'video.asset.errored', 'data' => ['id' => 'asset-1']])->assertJson(['duplicate' => true]);

        expect(LessonMedia::query()->sole()->failure)->toBe('content.files.failed.video');
    });
});

describe('search by meaning (A5.3, PostgreSQL with pgvector)', function () {
    beforeEach(function () {
        if (! Embedders::vectors()) {
            $this->markTestSkipped('Needs PostgreSQL with pgvector.');
        }
    });

    it('embeds passages with Titan and finds them by meaning', function () {
        // Two directions: "disclosure" passages point one way, everything else another.
        $vector = fn (bool $disclosure) => array_map(fn (int $i) => $i === ($disclosure ? 0 : 1) ? 1.0 : 0.0, range(0, 1023));
        $mock = new MockHandler;
        $requests = [];
        $answer = function ($command) use ($vector, &$requests) {
            $body = json_decode((string) $command['body'], true);
            $requests[] = $body;
            $disclosure = str_contains(strtolower($body['inputText']), 'iabs') || str_contains(strtolower($body['inputText']), 'who you represent');

            return new Result(['body' => json_encode(['embedding' => $vector($disclosure)])]);
        };
        for ($i = 0; $i < 10; $i++) {
            $mock->append($answer);
        }

        app(CredentialVault::class)->put('aws-bedrock-embeddings', CredentialEnvironment::Sandbox, [
            'access_key_id' => 'AKIATESTTESTTEST', 'secret_access_key' => 'test-secret', 'region' => 'us-east-2',
        ], null);
        IntegrationSetting::query()->create(['integration_id' => 'aws-bedrock-embeddings', 'mode' => IntegrationMode::Sandbox]);
        app()->instance(Embedders::HANDLER, $mock);

        $lesson = lesson($this->ops, course($this->ops), "# Disclosure\nHand them the IABS at first contact.\n\n# Fees\nCommissions are negotiable.");

        expect($lesson->passages()->whereNotNull('embedded_at')->count())->toBe(2)
            ->and($requests[0])->toMatchArray(['dimensions' => 1024, 'normalize' => true]);

        // No shared keyword, found by meaning.
        $hits = app(KnowledgeSearch::class)->search('explain who you represent', ['TX']);
        expect($hits)->toHaveCount(1)
            ->and($hits[0]->heading)->toBe('Disclosure')
            ->and($hits[0]->match)->toBe('meaning');

        expect((int) DB::selectOne('SELECT count(*) AS n FROM kb_passages WHERE embedding IS NOT NULL')->n)->toBe(2);
    });
});

describe('demo content (screen 63)', function () {
    it('seeds REOS courses that search finds, safely twice', function () {
        app(DemoContent::class)->seed();
        $passages = KbPassage::query()->count();
        app(DemoContent::class)->seed();

        expect(KbPassage::query()->count())->toBe($passages)
            ->and(Course::query()->where('slug', 'contracts-for-new-agents')->sole()->missingApprovals())->toBe(['TX']);

        $hits = app(KnowledgeSearch::class)->search('IABS', ['TX'], 'en');
        expect(collect($hits)->pluck('seconds')->filter()->all())->toContain(245)
            ->and(collect($hits)->pluck('heading')->all())->toContain('First contact');
    });
});
