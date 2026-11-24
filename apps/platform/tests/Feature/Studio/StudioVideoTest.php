<?php

declare(strict_types=1);

use App\Models\Connection;
use App\Models\MlsListing;
use App\Models\StudioVideo;
use App\Services\Ai\AiCredits;
use App\Studio\Video\FfmpegRenderer;
use App\Studio\Video\VideoRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\ExecutableFinder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'America/Chicago'));
    Storage::fake('content');
    Notification::fake();
    $this->artisan('reos:mls:sync --now')->assertSuccessful();
    $this->james = mlsAgent('James Miller', '0798765');
    $this->actingAs($this->james)->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertOk();
    $this->listing = MlsListing::query()->where('listing_key', 'HAR-L1')->sole();
});

function makeVideo(object $test, array $source, array $clips = []): array
{
    return $test->postJson('/api/v1/studio/videos', ['source' => $source, 'clips' => $clips])->assertCreated()->json('data');
}

describe('studio videos (B12.2)', function () {
    it('renders a 30-second listing video with a hook, captions and the brand frame, and posts it (the done-when)', function () {
        $left = app(AiCredits::class)->left($this->james);
        $home = $this->getJson('/api/v1/studio')->assertOk()->json('data');
        expect($home['video_cost'])->toBe(30)->and($home['tracks'])->toBe(['calm', 'bright', 'none'])->and($home['videos'])->toBe([]);

        $video = makeVideo($this, ['type' => 'listing', 'id' => $this->listing->uuid]);
        $photos = count($video['scenes']);
        expect($video['status'])->toBe('ready')
            ->and($video['title'])->toBe('Step inside this '.$this->listing->city.' home')
            ->and($photos)->toBeGreaterThan(1)
            ->and($video['scenes'][0]['caption'])->toStartWith('2418 Willow Bend Dr')
            ->and(end($video['scenes']))->toMatchArray(['media' => 'card', 'url' => '', 'caption' => 'Call James Miller to see it'])
            ->and($video['seconds'])->toEqual(round(array_sum(array_column($video['scenes'], 'seconds')), 1))
            ->and($video['seconds'])->toEqual(30)
            ->and($video['music'])->toBe('calm')
            ->and($video['issues'])->toBe([])
            ->and($video['video']['path'])->toEndWith('-v1.mp4')
            ->and(app(AiCredits::class)->left($this->james))->toBe($left - 30);

        // Phones need byte ranges to play it.
        $file = $this->get($video['video']['url'])->assertOk();
        expect($file->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
        $this->get($video['poster']['url'])->assertOk();

        connectOnWeb($this, $this->james, tool: 'meta')->assertRedirect();
        $facebook = ['connection' => Connection::query()->where('integration_id', 'meta')->sole()->id, 'channel' => 'facebook', 'id' => 'sim-page-1'];
        $post = $this->postJson('/api/v1/social/posts', ['body' => 'Take the tour! #JustListed', 'media' => [$video['video']['path']], 'listing' => $this->listing->uuid, 'targets' => [$facebook], 'when' => 'now'])->assertCreated()->json('data');
        expect($post['status'])->toBe('published')->and($post['media'][0]['kind'])->toBe('video');
    });

    it('edits a video, re-checks the words, fixes them, and renders again for free', function () {
        $video = makeVideo($this, ['type' => 'listing', 'id' => $this->listing->uuid]);
        $left = app(AiCredits::class)->left($this->james);
        $scenes = array_map(static fn (array $s): array => ['media' => $s['media'], 'seconds' => $s['seconds'], 'caption' => $s['caption']], $video['scenes']);
        $scenes = [$scenes[1], $scenes[0], ...array_slice($scenes, 2, -1)];
        $scenes[0]['caption'] = 'Perfect for families, guaranteed!';
        $scenes[0]['seconds'] = 30;

        $this->putJson("/api/v1/studio/videos/{$video['id']}", ['scenes' => $scenes])->assertUnprocessable()->assertJsonValidationErrors('scenes.0.seconds');
        $scenes[0]['seconds'] = 5;
        $edited = $this->putJson("/api/v1/studio/videos/{$video['id']}", ['scenes' => $scenes, 'title' => 'Come on in', 'music' => 'bright', 'brand' => false])->assertOk()->json('data');
        expect($edited['scenes'][0]['media'])->toBe($video['scenes'][1]['media'])
            ->and($edited['scenes'][0]['seconds'])->toEqual(5)
            ->and(count($edited['scenes']))->toBe(count($video['scenes']) - 1)
            ->and([$edited['title'], $edited['music'], $edited['brand'], $edited['status']])->toBe(['Come on in', 'bright', false, 'ready'])
            ->and(array_column($edited['issues'], 'phrase'))->toBe(['perfect for families', 'guaranteed']);

        $this->putJson("/api/v1/studio/videos/{$video['id']}", ['render' => true])->assertUnprocessable()->assertJsonValidationErrors('video');
        $fixed = $this->putJson("/api/v1/studio/videos/{$video['id']}", ['fix' => true, 'render' => true])->assertOk()->json('data');
        expect($fixed['scenes'][0]['caption'])->toBe('roomy layout!')
            ->and($fixed['issues'])->toBe([])
            ->and($fixed['status'])->toBe('ready')
            ->and($fixed['video']['path'])->toEndWith('-v2.mp4')
            ->and(Storage::disk('content')->exists($video['video']['path']))->toBeFalse()
            ->and(Storage::disk('content')->exists($fixed['video']['path']))->toBeTrue();

        $hooks = $this->postJson("/api/v1/studio/videos/{$video['id']}/hooks")->assertOk()->json('data');
        expect($hooks)->toHaveCount(3)->and(app(AiCredits::class)->left($this->james))->toBe($left);
    });

    it('makes a video from my own clips and photos, and keeps them mine', function () {
        $this->postJson('/api/v1/studio/videos', ['source' => ['type' => 'own']])->assertUnprocessable()->assertJsonValidationErrors('clips');
        $clip = $this->post('/api/v1/social/media', ['file' => UploadedFile::fake()->create('tour.mp4', 200, 'video/mp4')], ['Accept' => 'application/json'])->assertCreated()->json('data.path');
        $photo = $this->post('/api/v1/social/media', ['file' => UploadedFile::fake()->image('kitchen.jpg', 1200, 900)], ['Accept' => 'application/json'])->assertCreated()->json('data.path');

        $video = makeVideo($this, ['type' => 'own', 'text' => 'Open house Saturday'], [$clip, $photo]);
        expect(array_column($video['scenes'], 'kind'))->toBe(['image', 'video', 'image'])
            ->and(end($video['scenes'])['media'])->toBe('card')
            ->and($video['scenes'][0]['caption'])->toBe('Open house Saturday')
            ->and($video['source'])->toBe(['type' => 'own', 'id' => null, 'label' => 'Open house Saturday'])
            ->and($video['status'])->toBe('ready');

        $this->postJson('/api/v1/studio/videos', ['source' => ['type' => 'own'], 'clips' => ['social/999/someone.mp4']])->assertUnprocessable()->assertJsonValidationErrors('clips');
        $other = mlsAgent('Maria Garcia', '0712345');
        $this->actingAs($other)->getJson("/api/v1/studio/videos/{$video['id']}")->assertNotFound();
        $this->actingAs($other)->putJson("/api/v1/studio/videos/{$video['id']}", ['title' => 'Mine'])->assertNotFound();
    });

    it('says so when a render fails, and keeps it out of the way while rendering', function () {
        app()->bind(VideoRenderer::class, static fn () => new class implements VideoRenderer
        {
            public function engine(): string
            {
                return 'ffmpeg';
            }

            public function render(array $plan, string $dir): array
            {
                throw new RuntimeException('no ffmpeg');
            }

            public function probe(string $file): array
            {
                return ['seconds' => null, 'sound' => false];
            }
        });
        $video = makeVideo($this, ['type' => 'listing', 'id' => $this->listing->uuid]);
        expect($video['status'])->toBe('failed')->and($video['error'])->toBe("The video didn't render. Try again.")->and($video['video'])->toBeNull();

        StudioVideo::query()->where('uuid', $video['id'])->update(['status' => 'rendering']);
        $this->putJson("/api/v1/studio/videos/{$video['id']}", ['title' => 'Again'])->assertUnprocessable()->assertJsonValidationErrors('video');
    });

    it('renders a real 30-second vertical MP4 with ffmpeg', function () {
        $finder = new ExecutableFinder;

        if ($finder->find('ffmpeg') === null || $finder->find('ffprobe') === null) {
            $this->markTestSkipped('ffmpeg is not installed here.');
        }

        app()->bind(VideoRenderer::class, static fn () => new FfmpegRenderer);
        $video = makeVideo($this, ['type' => 'listing', 'id' => $this->listing->uuid]);
        expect($video['status'])->toBe('ready')->and($video['engine'])->toBe('ffmpeg');

        $local = tempnam(sys_get_temp_dir(), 'reos-video');
        file_put_contents($local, Storage::disk('content')->get($video['video']['path']));
        $probe = json_decode((string) shell_exec('ffprobe -v error -show_entries stream=codec_type,width,height:format=duration -of json '.escapeshellarg($local)), true);
        unlink($local);
        expect((float) $probe['format']['duration'])->toEqualWithDelta($video['seconds'], 0.5)
            ->and(collect($probe['streams'])->firstWhere('codec_type', 'video'))->toMatchArray(['width' => 1080, 'height' => 1920])
            ->and(collect($probe['streams'])->pluck('codec_type')->all())->toContain('audio');
    })->group('ffmpeg');
});
