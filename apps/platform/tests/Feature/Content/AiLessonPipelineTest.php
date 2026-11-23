<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Content\Ai\LessonPipeline;
use App\Content\Avatars\HeyGenVideo;
use App\Content\Avatars\SynthesiaVideo;
use App\Content\Builder\LessonReview;
use App\Content\Builder\SourceRole;
use App\Content\CourseStatus;
use App\Content\Extraction\Captions;
use App\Content\Learning\Learning;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Enums\OrganisationType;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\AvatarConsent;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\IntegrationSetting;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\LessonRender;
use App\Models\LessonVersion;
use App\Models\Organisation;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Ai\AiCredits;
use App\Services\Content\LessonFiles;
use App\Voice\UnderstandingFactory;
use Aws\MockHandler;
use Aws\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

const AI_SCRIPT = 'In Texas, a license holder must tell each party who they represent at the first substantive contact. '
    .'They also give the Information About Brokerage Services form at that first contact. '
    .'The form explains the types of representation a broker may offer.';

beforeEach(function () {
    Storage::fake('local');
    Storage::fake(config()->string('reos.content.disk'));
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
    $this->rachel = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Dr. Rachel Hughes']);
    $this->course = Course::query()->create(['organisation_id' => $this->school->id, 'title' => 'Principles of Real Estate II', 'slug' => 'principles-ii', 'type' => 'qualifying', 'states' => ['TX'], 'hours' => 30]);
    $this->course->instructors()->attach($this->rachel->id);
    $this->module = CourseModule::query()->create(['course_id' => $this->course->id, 'title' => 'Agency in Texas', 'position' => 1]);
    aiSource($this->module, AI_SCRIPT);
    aiCredits($this->school, 1000);
});

function aiSource(CourseModule $module, string $text, string $name = 'Agency script.pdf'): void
{
    $module->sources()->create([
        'role' => SourceRole::Script, 'kind' => MediaKind::Document, 'path' => 'tenants/'.$module->course->organisation_id.'/courses/'.$module->course_id.'/sources/'.uniqid().'.pdf',
        'original_name' => $name, 'mime' => 'application/pdf', 'size' => 1000, 'status' => MediaStatus::Ready, 'scanned_at' => now(),
        'sections' => [['page' => 3, 'text' => $text]], 'pages' => 1, 'words' => str_word_count($text), 'position' => $module->sources()->count() + 1,
    ]);
}

function aiCredits(Organisation $school, int $credits): void
{
    $ledger = app(Ledger::class);
    $ledger->post('pool_funded', [[$ledger->platform('credits_sold'), -$credits], [$ledger->organisation($school, 'credits'), $credits]], 'organisation', (string) $school->id, 'Test');
}

function aiGenerate(object $test, ?User $as = null): void
{
    $test->actingAs($as ?? $test->rachel)->post("/course-builder/modules/{$test->module->id}/generate")->assertSessionHasNoErrors()->assertRedirect();
}

function aiVendorKeys(string $vendor, array $keys): void
{
    app(CredentialVault::class)->put($vendor, CredentialEnvironment::Sandbox, $keys, null);
    IntegrationSetting::query()->create(['integration_id' => $vendor, 'mode' => IntegrationMode::Sandbox]);
}

describe('one lesson end to end (the A7.3 done-when)', function () {
    it('drafts the script from the sources with citations, renders English and Spanish with captions, and queues the review', function () {
        aiGenerate($this);

        $lesson = Lesson::query()->where('course_module_id', $this->module->id)->sole();
        $version = $lesson->versions()->sole();

        // Sources → script with citations, in both languages, checked line by line.
        expect($version->status)->toBe(LessonVersion::RENDERING)
            ->and($version->lines)->toHaveCount(3)
            ->and($version->lines[0])->toMatchArray(['n' => 0, 'source' => 'Agency script.pdf', 'page' => 3])
            ->and($version->lines[0]['en'])->toStartWith('In Texas, a license holder must tell')
            ->and($version->lines[0]['es'])->toStartWith('[ES] In Texas')
            ->and($version->flags)->toBe([])
            ->and($version->vendor)->toBe('heygen')
            ->and($version->renders()->pluck('language')->all())->toBe(['en', 'es'])
            ->and($lesson->review_status)->toBe(LessonReview::Generating);

        // The vendor (here its simulator) finishes: videos copied into REOS's storage and video pipeline.
        $this->artisan('reos:lessons:renders')->assertSuccessful();

        $lesson->refresh();
        $videos = $lesson->media()->where('kind', MediaKind::Video->value)->orderBy('language')->get();
        expect($lesson->review_status)->toBe(LessonReview::InReview)
            ->and($version->refresh()->status)->toBe(LessonVersion::READY)
            ->and($videos->pluck('language')->all())->toBe(['en', 'es'])
            ->and($videos->pluck('status')->all())->toBe([MediaStatus::Ready, MediaStatus::Ready])
            ->and($videos[0]->path)->toStartWith("tenants/{$this->school->id}/lessons/{$lesson->id}/");
        LessonFiles::disk()->assertExists($videos[0]->path);

        // Each video has captions in its own language and the other one.
        $english = $videos[0];
        expect(array_keys($english->captions ?? []))->toBe(['en', 'es'])
            ->and(Captions::cues($english->captions['en'] ?? '')[0]['text'])->toStartWith('In Texas')
            ->and(Captions::cues($english->captions['es'] ?? '')[0]['text'])->toStartWith('[ES] In Texas')
            ->and($english->transcript)->toBe($english->captions['en']);

        // Check-ins, questions and flashcards wait for review (A8.1).
        $items = ReviewItem::query()->where('lesson_version_id', $version->id)->get();
        expect($items->pluck('status')->unique()->all())->toBe([ReviewItem::TO_REVIEW])
            ->and($items->where('kind', 'question')->count())->toBeGreaterThan(0)
            ->and($items->where('kind', 'flashcard')->count())->toBeGreaterThan(0);
        $question = $items->firstWhere('kind', 'question');
        expect($question->content)->toHaveKeys(['en', 'es', 'correct', 'source', 'page'])
            ->and($question->content['source'])->toBe('Agency script.pdf')
            ->and($question->topic)->toBe('state_rules');

        // Charged to the school, never the instructor; one credit row per version.
        expect(app(AiCredits::class)->schoolLeft($this->school))->toBe(1000 - $version->credits)
            ->and($version->credits)->toBe(20) // 1 minute × 2 voices × 10 credits
            ->and(DB::table('ai_credit_uses')->where('feature', 'ai_lesson')->where('user_id', $this->rachel->id)->count())->toBe(1)
            ->and(AuditEvent::query()->where('action', 'module.generated')->where('actor_id', $this->rachel->id)->exists())->toBeTrue();
    });

    it('shows the draft side by side, with each line\'s source and both videos', function () {
        aiGenerate($this);
        $this->artisan('reos:lessons:renders');
        $lesson = Lesson::query()->where('course_module_id', $this->module->id)->sole();

        $this->actingAs($this->rachel)->get("/course-builder/{$this->course->id}?module={$this->module->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('builder/course')
                ->where('module.lessons.0.ai', ['version' => 1, 'status' => 'ready', 'flags' => 0])
                ->where('module.lessons.0.status', 'in_review')
                ->where('module.plan.credits_left', 980)
                ->where('vendor_live', false));

        $this->actingAs($this->rachel)->getJson("/course-builder/lessons/{$lesson->id}/draft")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.lines.0.source', 'Agency script.pdf')
            ->assertJsonPath('data.lines.0.page', 3)
            ->assertJsonPath('data.renders.0.language', 'en')
            ->assertJsonPath('data.renders.0.simulated', true)
            ->assertJsonPath('data.renders.1.language', 'es')
            ->assertJsonCount(2, 'data.renders');

        $marcus = User::factory()->withRole(Role::Instructor, $this->school)->create();
        $this->actingAs($marcus)->getJson("/course-builder/lessons/{$lesson->id}/draft")->assertNotFound();
    });

    it('gives learners the video in their language, with captions in it', function () {
        aiGenerate($this);
        $this->artisan('reos:lessons:renders');
        $lesson = Lesson::query()->where('course_module_id', $this->module->id)->sole();
        $learning = app(Learning::class);

        $english = $learning->lesson($lesson, 'en');
        $spanish = $learning->lesson($lesson, 'es');
        $esVideo = LessonMedia::query()->where('lesson_id', $lesson->id)->where('language', 'es')->sole();

        expect($spanish['video'])->not->toBeNull()
            ->and($spanish['video']['url'])->toContain((string) $esVideo->id)
            ->and($spanish['transcript'][0]['text'])->toStartWith('[ES]')
            ->and($english['transcript'][0]['text'])->toStartWith('In Texas');
    });

    it('keeps earlier versions when a lesson is generated again', function () {
        aiGenerate($this);
        $this->artisan('reos:lessons:renders');
        $lesson = Lesson::query()->where('course_module_id', $this->module->id)->sole();
        $first = LessonMedia::query()->where('lesson_id', $lesson->id)->pluck('id')->all();

        $this->actingAs($this->rachel)->post("/course-builder/lessons/{$lesson->id}/regenerate")->assertSessionHasNoErrors();
        $this->artisan('reos:lessons:renders');

        expect($lesson->versions()->pluck('version')->all())->toBe([1, 2])
            ->and($lesson->versions()->pluck('status')->all())->toBe([LessonVersion::READY, LessonVersion::READY])
            // The new version's videos replace the old ones; the old version's script is kept.
            ->and(LessonMedia::query()->whereIn('id', $first)->exists())->toBeFalse()
            ->and(LessonMedia::query()->where('lesson_id', $lesson->id)->count())->toBe(2)
            ->and(AuditEvent::query()->where('action', 'lesson.regenerated')->exists())->toBeTrue();
    });
});

describe('what stops a lesson', function () {
    it('flags a legal line in either language and renders nothing', function () {
        $this->module->sources()->delete();
        aiSource($this->module, 'Pass with us and your license is guaranteed by the end of the month. Study the agency rules every day.');

        aiGenerate($this);
        $version = LessonVersion::query()->sole();

        expect($version->status)->toBe(LessonVersion::FLAGGED)
            ->and($version->flags)->toContain(['line' => 0, 'rule' => 'guarantee', 'language' => 'en'])
            ->and($version->renders()->count())->toBe(0)
            ->and($version->lesson->review_status)->toBe(LessonReview::InReview);
    });

    it('needs sources, enough school credits and no generation already running', function () {
        $empty = CourseModule::query()->create(['course_id' => $this->course->id, 'title' => 'Contracts', 'position' => 2]);
        $this->actingAs($this->rachel)->post("/course-builder/modules/{$empty->id}/generate")->assertSessionHasErrors('module');

        $poor = Organisation::factory()->ofType(OrganisationType::School)->create();
        $this->course->forceFill(['organisation_id' => $poor->id])->save();
        $admin = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $poor)->create();
        $this->actingAs($admin)->post("/course-builder/modules/{$this->module->id}/generate")->assertSessionHasErrors('module');
        expect(Lesson::query()->count())->toBe(0);

        $this->course->forceFill(['organisation_id' => $this->school->id])->save();
        Queue::fake();
        aiGenerate($this);
        $this->actingAs($this->rachel)->post("/course-builder/modules/{$this->module->id}/generate")->assertSessionHasErrors('module');
        expect(Lesson::query()->count())->toBe(1);
    });

    it('uses an instructor\'s avatar only while their consent is in force and their twin is set up', function () {
        $this->actingAs($this->rachel)->post('/settings/avatar', ['agree' => '1', 'sample' => UploadedFile::fake()->create('sample.mp4', 40_000, 'video/mp4')])->assertSessionHasNoErrors();
        $this->actingAs($this->rachel)->put("/course-builder/{$this->course->id}/settings", ['language' => 'both', 'lesson_minutes' => 10, 'presenter' => "instructor:{$this->rachel->id}", 'vendor' => 'heygen'])->assertSessionHasNoErrors();

        // HeyGen connected: the twin's id must be recorded on screen 52 first.
        aiVendorKeys('heygen', ['api_key' => 'hg-test', 'stock_avatar_id' => 'stock-1', 'voice_id_en' => 'voice-en', 'voice_id_es' => 'voice-es']);
        $this->actingAs($this->rachel)->post("/course-builder/modules/{$this->module->id}/generate")->assertSessionHasErrors('presenter');

        $this->actingAs($this->paul)->put("/school/instructors/{$this->rachel->id}/twins", ['heygen' => 'twin_rachel_01', 'synthesia' => null])->assertSessionHasNoErrors();
        expect(AvatarConsent::query()->sole()->vendor_avatars)->toBe(['heygen' => 'twin_rachel_01'])
            ->and(AuditEvent::query()->where('action', 'avatar.twins_recorded')->where('actor_id', $this->paul->id)->exists())->toBeTrue();

        Http::fake(['api.heygen.com/v2/video/generate' => Http::response(['data' => ['video_id' => 'vid-1']])]);
        aiGenerate($this);
        Http::assertSent(fn ($request) => $request['video_inputs'][0]['character']['avatar_id'] === 'twin_rachel_01');

        // Withdrawn: the avatar can't be used any more.
        $this->actingAs($this->rachel)->delete('/settings/avatar')->assertSessionHasNoErrors();
        $this->actingAs($this->paul)->put("/school/instructors/{$this->rachel->id}/twins", ['heygen' => 'x'])->assertSessionHasErrors('twins');
    });

    it('marks a version failed when the vendor fails, and the lesson goes back to draft', function () {
        aiVendorKeys('synthesia', ['api_key' => 'syn-test', 'stock_avatar' => 'anna_costume1_cameraA']);
        $this->actingAs($this->rachel)->put("/course-builder/{$this->course->id}/settings", ['language' => 'both', 'lesson_minutes' => 10, 'presenter' => 'stock', 'vendor' => 'synthesia'])->assertSessionHasNoErrors();
        Http::fake([
            'api.synthesia.io/v2/videos' => Http::sequence()->push(['id' => 'syn-en'])->push(['id' => 'syn-es']),
            'api.synthesia.io/v2/videos/syn-en' => Http::response(['id' => 'syn-en', 'status' => 'error']),
            'api.synthesia.io/v2/videos/syn-es' => Http::response(['id' => 'syn-es', 'status' => 'in_progress']),
        ]);

        aiGenerate($this);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.synthesia.io/v2/videos' && $request['test'] === true && $request['input'][0]['scriptLanguage'] === 'es-MX' && $request['input'][0]['avatar'] === 'anna_costume1_cameraA');
        $this->artisan('reos:lessons:renders');

        $version = LessonVersion::query()->sole();
        expect($version->status)->toBe(LessonVersion::FAILED)
            ->and($version->renders()->where('language', 'en')->value('status'))->toBe(LessonRender::FAILED)
            ->and($version->lesson->review_status)->toBe(LessonReview::Draft);
    });
});

describe('the vendors', function () {
    it('renders at HeyGen with the language\'s voice, copies the video in and deletes HeyGen\'s copy', function () {
        aiVendorKeys('heygen', ['api_key' => 'hg-test', 'stock_avatar_id' => 'stock-1', 'voice_id_en' => 'voice-en', 'voice_id_es' => 'voice-es']);
        Http::fake([
            'api.heygen.com/v2/video/generate' => Http::sequence()->push(['data' => ['video_id' => 'hg-en']])->push(['data' => ['video_id' => 'hg-es']]),
            'api.heygen.com/v1/video_status.get*' => Http::response(['data' => ['status' => 'completed', 'video_url' => 'https://files.heygen.example/v.mp4', 'duration' => 41.3]]),
            'files.heygen.example/*' => Http::response((string) file_get_contents(resource_path('content/simulated-lesson.mp4'))),
            'api.heygen.com/v1/video.delete*' => Http::response(['code' => 100]),
        ]);

        aiGenerate($this);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'video/generate') && $request->header('x-api-key') === ['hg-test']
            && $request['video_inputs'][0]['voice']['voice_id'] === 'voice-es' && $request['video_inputs'][0]['character']['avatar_id'] === 'stock-1');
        $this->artisan('reos:lessons:renders');

        $render = LessonRender::query()->where('language', 'en')->sole();
        expect($render->status)->toBe(LessonRender::READY)
            ->and($render->simulated)->toBeFalse()
            ->and($render->seconds)->toBe(42)
            ->and(LessonMedia::query()->findOrFail($render->lesson_media_id)->duration_seconds)->toBe(42);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'video.delete') && str_contains($request->url(), 'hg-en'));
    });

    it('reads Synthesia\'s lengths and statuses', function () {
        Http::fake(['api.synthesia.io/v2/videos/a' => Http::response(['status' => 'complete', 'download' => 'https://x.example/a.mp4', 'duration' => '0:01:31.2'])]);

        expect((new SynthesiaVideo(['api_key' => 'k'], true))->check('a'))->toBe(['status' => 'ready', 'url' => 'https://x.example/a.mp4', 'seconds' => 92, 'error' => null])
            ->and((new HeyGenVideo(['api_key' => 'k']))->stockAvatar())->toBe('');
    });

    it('writes captions timed to the video, line by line', function () {
        $vtt = Captions::write(['One two three.', 'Four five six seven eight nine.'], 30);

        expect(Captions::cues($vtt))->toBe([['seconds' => 0, 'text' => 'One two three.'], ['seconds' => 9, 'text' => 'Four five six seven eight nine.']])
            ->and($vtt)->toContain('00:00:30.000');
    });
});

describe('Claude writes and translates', function () {
    it('writes from the sources only, as checked data, and translates line by line', function () {
        $mock = new MockHandler;
        $mock->append(new Result(['output' => ['message' => ['role' => 'assistant', 'content' => [['toolUse' => ['toolUseId' => 't1', 'name' => 'save_lesson', 'input' => [
            'title' => 'Telling people who you represent',
            'lines' => [
                ['text' => 'In Texas, you say who you represent at the first substantive contact.', 'source' => 0],
                ['text' => 'A line citing a source that does not exist.', 'source' => 7],
            ],
            'checkins' => [['after_line' => 0, 'prompt' => 'When do you say who you represent?']],
            'questions' => [['prompt' => 'When is the IABS form given?', 'options' => ['At the first substantive contact', 'At closing', 'Never', 'After the offer'], 'correct' => 0, 'explanation' => 'At first contact.', 'source' => 0, 'topic' => 'agency']],
            'flashcards' => [['front' => 'IABS', 'back' => 'Information About Brokerage Services', 'source' => 0]],
        ]]]]]], 'stopReason' => 'tool_use']));
        $mock->append(new Result(['output' => ['message' => ['role' => 'assistant', 'content' => [['toolUse' => ['toolUseId' => 't2', 'name' => 'save_translation', 'input' => ['lines' => [
            'Cómo decir a quién representa',
            'En Texas, usted dice a quién representa en el primer contacto sustancial.',
            '¿Cuándo dice a quién representa?',
            '¿Cuándo se entrega el formulario IABS?', 'En el primer contacto sustancial', 'Al cierre', 'Nunca', 'Después de la oferta', 'En el primer contacto.',
            'IABS', 'Información sobre Servicios de Corretaje',
        ]]]]]]], 'stopReason' => 'tool_use']));
        app(CredentialVault::class)->put('aws-bedrock-claude', CredentialEnvironment::Sandbox, ['access_key_id' => 'AKIATEST', 'secret_access_key' => 's', 'region' => 'us-east-2', 'model_id' => 'us.anthropic.claude-test'], null);
        IntegrationSetting::query()->create(['integration_id' => 'aws-bedrock-claude', 'mode' => IntegrationMode::Sandbox]);
        app()->instance(UnderstandingFactory::HANDLER, $mock);

        aiGenerate($this);

        $version = LessonVersion::query()->sole();
        expect($version->lines)->toBe([[
            'n' => 0, 'en' => 'In Texas, you say who you represent at the first substantive contact.',
            'es' => 'En Texas, usted dice a quién representa en el primer contacto sustancial.', 'source' => 'Agency script.pdf', 'page' => 3,
        ]])
            ->and($version->lesson->title)->toBe('Telling people who you represent')
            ->and(ReviewItem::query()->where('kind', 'question')->sole()->content['es']['options'])->toBe(['En el primer contacto sustancial', 'Al cierre', 'Nunca', 'Después de la oferta'])
            ->and(ReviewItem::query()->where('kind', 'question')->sole()->topic)->toBe('agency')
            ->and(ReviewItem::query()->where('kind', 'flashcard')->sole()->content['es'])->toBe(['front' => 'IABS', 'back' => 'Información sobre Servicios de Corretaje'])
            ->and(DB::table('ai_credit_uses')->where('feature', LessonPipeline::FEATURE)->value('engine'))->toBe('ai');
    });
});

it('keeps library courses (no school) out of AI generation', function () {
    $library = Course::query()->create(['title' => 'REOS library course', 'slug' => 'library', 'type' => 'qualifying', 'states' => ['TX'], 'status' => CourseStatus::Draft]);
    $module = CourseModule::query()->create(['course_id' => $library->id, 'title' => 'Agency', 'position' => 1]);

    expect(fn () => app(LessonPipeline::class)->generate($library, $module, 15, $this->paul))->toThrow(ValidationException::class);
});
