<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VideoPractice;
use App\Voice\UnderstandingFactory;
use Aws\MockHandler;
use Aws\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Video practice with an AI review (A14.1b, ADR 0061): the words and the
 * length only; pace, fillers, a score, three fixes and the compliance
 * check; private to the agent.
 */

beforeEach(function () {
    $this->org = Organisation::factory()->create(['name' => 'Lone Star Realty']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->org)->create(['name' => 'Maria Garcia']);
});

/** A 78-word pitch: introduction, proof, a clear close. */
const PITCH = 'Hi, I am Maria Garcia with Lone Star Realty. I help families in Katy sell their homes with confidence. '
    .'This year I sold fourteen homes in Cinco Ranch, most of them in under twenty days, and my clients got close to their asking price. '
    .'My plan covers pricing from real market data, professional photos and a video tour. '
    .'If you are thinking of selling, call or text me today and I will send you a free price review.';

describe('video practice (A14.1b)', function () {
    it('reviews a recording: pace, fillers, a score and three fixes (the done-when)', function () {
        $this->actingAs($this->maria)->getJson('/api/v1/video-practice')->assertOk()
            ->assertJsonPath('data.kinds', ['listing_pitch', 'video_intro'])
            ->assertJsonPath('data.max_seconds', 120)
            ->assertJsonPath('data.recent', []);

        $review = $this->postJson('/api/v1/video-practice', ['kind' => 'listing_pitch', 'transcript' => PITCH, 'duration_seconds' => 30])->assertOk()->json('data');
        expect($review)->toMatchArray(['kind' => 'listing_pitch', 'words' => 78, 'wpm' => 156, 'pace' => 'good', 'fillers' => [], 'engine' => 'script'])
            ->and($review['scores'])->toBe(['pace' => 20, 'fillers' => 20, 'opening' => 20, 'proof' => 20, 'close' => 20])
            ->and($review['score'])->toBe(100)
            ->and($review['fixes'])->toHaveCount(3)
            // The broker's name was said, so nothing to fix before posting.
            ->and(array_column($review['compliance'], 'code'))->not->toContain('broker');

        $this->getJson("/api/v1/video-practice/{$review['id']}")->assertOk()->assertJsonPath('data.transcript', PITCH);
        $this->getJson('/api/v1/video-practice')->assertJsonPath('data.recent.0', ['id' => $review['id'], 'kind' => 'listing_pitch', 'score' => 100, 'wpm' => 156, 'duration_seconds' => 30, 'created_at' => $review['created_at']]);
    });

    it('finds fillers, a fast pace, a missing close and what to fix before posting', function () {
        $rushed = 'Um so basically I am you know the best agent, um, I guarantee your home will sell fast, uh, basically I know the neighborhood, um, you know, '
            .'it is kind of a great market and I mean everything is great, um, um, I sell homes and that is it, basically, you know, um.';
        $review = $this->actingAs($this->maria)->postJson('/api/v1/video-practice', ['kind' => 'video_intro', 'transcript' => $rushed, 'duration_seconds' => 15])->assertOk()->json('data');

        expect($review['pace'])->toBe('fast')
            ->and($review['fillers'][0])->toBe(['word' => 'um', 'count' => 6])
            ->and($review['scores']['fillers'])->toBe(2)
            ->and($review['scores']['close'])->toBe(0)
            ->and(array_column($review['fixes'], 'dimension'))->toContain('close', 'fillers')
            ->and($review['fixes'][0]['text'])->toBe(__('videopractice.fixes.'.$review['fixes'][0]['dimension'].'.text'))
            // "I guarantee" is a misleading claim, and no broker is named.
            ->and(array_column($review['compliance'], 'code'))->toContain('claims', 'broker');
    });

    it('turns away recordings too short to review', function () {
        $this->actingAs($this->maria)->postJson('/api/v1/video-practice', ['kind' => 'listing_pitch', 'transcript' => 'Hi, I am Maria.', 'duration_seconds' => 4])
            ->assertUnprocessable()->assertJsonValidationErrors('transcript');
        $this->postJson('/api/v1/video-practice', ['kind' => 'listing_pitch', 'transcript' => PITCH, 'duration_seconds' => 600])->assertUnprocessable()->assertJsonValidationErrors('duration_seconds');
        $this->postJson('/api/v1/video-practice', ['kind' => 'tiktok', 'transcript' => PITCH, 'duration_seconds' => 30])->assertUnprocessable()->assertJsonValidationErrors('kind');
        expect(VideoPractice::query()->count())->toBe(0);
    });

    it('lets Claude review it, and falls back to REOS when Claude fails', function () {
        app(CredentialVault::class)->put('aws-bedrock-claude', CredentialEnvironment::Sandbox, [
            'access_key_id' => 'AKIATESTTESTTEST', 'secret_access_key' => 'test-secret', 'region' => 'us-east-2', 'model_id' => 'us.anthropic.claude-test',
        ], null);
        IntegrationSetting::query()->create(['integration_id' => 'aws-bedrock-claude', 'mode' => IntegrationMode::Sandbox]);
        $mock = new MockHandler;
        app()->instance(UnderstandingFactory::HANDLER, $mock);
        $tool = static fn (array $input): Result => new Result(['output' => ['message' => ['role' => 'assistant', 'content' => [['toolUse' => ['toolUseId' => 't1', 'name' => 'review', 'input' => $input]]]]], 'stopReason' => 'tool_use']);
        $mock->append($tool([
            'scores' => ['pace' => 18, 'fillers' => 20, 'opening' => 16, 'proof' => 14, 'close' => 12],
            'fixes' => [
                ['dimension' => 'close', 'text' => 'Give a day for the price review.', 'example' => 'Text me and I will send it by Friday.'],
                ['dimension' => 'proof', 'text' => 'Name one sale.', 'example' => 'I sold 12 Willow Bend in nine days.'],
                ['dimension' => 'opening', 'text' => 'Smile and slow the first line.', 'example' => 'Hi, I am Maria Garcia.'],
            ],
        ]));

        $review = $this->actingAs($this->maria)->postJson('/api/v1/video-practice', ['kind' => 'listing_pitch', 'transcript' => PITCH, 'duration_seconds' => 30])->json('data');
        expect($review)->toMatchArray(['score' => 80, 'engine' => 'claude'])
            ->and($review['fixes'][1])->toBe(['dimension' => 'proof', 'text' => 'Name one sale.', 'example' => 'I sold 12 Willow Bend in nine days.', 'lesson' => 'listing_presentation'])
            ->and($review['wpm'])->toBe(156);

        $mock->append($tool(['scores' => ['pace' => 1], 'fixes' => []]));
        $this->postJson('/api/v1/video-practice', ['kind' => 'listing_pitch', 'transcript' => PITCH, 'duration_seconds' => 30])->assertJsonPath('data.engine', 'script')->assertJsonPath('data.score', 100);
    });

    it('keeps reviews private to the agent and open only to agents', function () {
        $id = $this->actingAs($this->maria)->postJson('/api/v1/video-practice', ['kind' => 'listing_pitch', 'transcript' => PITCH, 'duration_seconds' => 30])->json('data.id');
        $james = User::factory()->withRole(Role::Agent, $this->org)->create();
        $this->actingAs($james)->getJson("/api/v1/video-practice/{$id}")->assertNotFound();
        $this->getJson('/api/v1/video-practice')->assertJsonPath('data.recent', []);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/video-practice')->assertNotFound();
    });

    it('works in Spanish', function () {
        $this->maria->forceFill(['locale' => 'es'])->save();
        $pitch = 'Hola, me llamo Maria Garcia de Lone Star Realty y ayudo a las familias de Katy a vender su casa con confianza. Este año vendí catorce casas en Cinco Ranch, '
            .'la mayoría en menos de veinte días, con datos reales del mercado. Si está pensando en vender, llámeme o escríbame hoy y le envío una revisión de precio gratis.';
        $review = $this->actingAs($this->maria)->postJson('/api/v1/video-practice', ['kind' => 'listing_pitch', 'transcript' => $pitch, 'duration_seconds' => 25])->json('data');
        expect($review['scores'])->toMatchArray(['opening' => 20, 'proof' => 20, 'close' => 20])
            ->and($review['fixes'][0]['text'])->toBe(__('videopractice.fixes.'.$review['fixes'][0]['dimension'].'.text', [], 'es'));
    });
});

it('records and reviews on the web', function () {
    $this->actingAs($this->maria)->get('/video-practice')->assertOk()->assertInertia(fn (Assert $page) => $page->component('video-practice/index')->where('max_seconds', 120)->has('recent', 0));
    $this->post('/video-practice', ['kind' => 'video_intro', 'transcript' => PITCH, 'duration_seconds' => 30])->assertRedirect();
    $practice = VideoPractice::query()->sole();
    $this->get("/video-practice/{$practice->uuid}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('video-practice/show')->where('review.kind', 'video_intro')->has('review.fixes', 3));
});
