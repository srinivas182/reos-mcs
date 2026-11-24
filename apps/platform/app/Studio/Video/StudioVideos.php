<?php

declare(strict_types=1);

namespace App\Studio\Video;

use App\Jobs\RenderStudioVideo;
use App\Models\StudioVideo;
use App\Models\User;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use App\Services\Ai\Bedrock;
use App\Social\SocialPosts;
use App\Studio\AdvertisingPolicy;
use App\Studio\ClaudeStudioWriter;
use App\Studio\StudioGenerator;
use App\Studio\StudioRenderer;
use App\Tenancy\Brand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Studio videos (B12.2, ADR 0051; agent screens 166 and 168, web screen 17).
 *
 * A listing video is made from the listing's photos (the ones the MLS lets
 * the agent post) and the agent's own clips and photos: about 30 seconds,
 * vertical, with a hook for the first two seconds, a caption on every
 * scene, the agent's card at the end, the brand frame with the agent and
 * broker, and REOS-made music.
 * Making one costs `short_video` credits (30 by default), shown first.
 *
 * Editing (screen 168) changes the hook, the scenes' order, length, trim
 * and captions, the music and the brand colour; every edit is checked
 * again (fair housing, claims, the brokerage's banned phrases) and the
 * result shown, and a video with problems isn't rendered until they're
 * fixed. Rendering again after an edit is free; "Hook" asks for stronger
 * first words, also free.
 *
 * @phpstan-import-type Scene from StudioVideo
 * @phpstan-import-type Issue from AdvertisingPolicy
 * @phpstan-import-type Facts from StudioGenerator
 * @phpstan-import-type Plan from VideoRenderer
 *
 * @phpstan-type SceneRow array{media: string, url: string, kind: 'image'|'video', start: float, seconds: float, length: float|null, caption: string}
 * @phpstan-type VideoRow array{id: string, source: array{type: 'listing'|'own', id: string|null, label: string|null}, listing: string|null, title: string, scenes: list<SceneRow>, music: string, brand: bool, status: string, seconds: float|null, video: array{path: string, url: string}|null, poster: array{path: string, url: string}|null, error: string|null, issues: list<Issue>, credits: int, engine: string, created_at: string|null, rendered_at: string|null}
 */
final readonly class StudioVideos
{
    public const TARGET_SECONDS = 30;

    public const MAX_SECONDS = 60;

    public const MAX_SCENES = 12;

    public const HOOK_SECONDS = 2.0;

    public const MIN_SCENE = 1.5;

    public const MAX_SCENE = 10.0;

    private const PHOTOS = 8;

    /** The closing card's scene: the brand colour with the call. */
    public const CARD = 'card';

    public function __construct(
        private StudioGenerator $generator,
        private AiCredits $credits,
        private AiSettings $settings,
        private Bedrock $bedrock,
        private AdvertisingPolicy $policy,
        private VideoRenderer $renderer,
    ) {}

    public function cost(): int
    {
        return max(0, $this->settings->cost('short_video'));
    }

    /** @return list<VideoRow> */
    public function recent(User $agent): array
    {
        $rows = [];

        foreach (StudioVideo::query()->where('user_id', $agent->id)->with('user')->latest('id')->limit(10)->get() as $video) {
            $rows[] = $this->row($video);
        }

        return $rows;
    }

    public function own(User $agent, string $uuid): StudioVideo
    {
        return StudioVideo::query()->where('user_id', $agent->id)->where('uuid', $uuid)->with('user')->firstOrFail();
    }

    /**
     * A new video from a listing or the agent's own clips and photos, rendered in the queue.
     *
     * @param  array{type: 'listing'|'own', id?: string|null, text?: string|null}  $source
     * @param  list<string>  $clips  the agent's uploaded files (social/<id>/…)
     */
    public function make(User $agent, array $source, array $clips): StudioVideo
    {
        $cost = $this->cost();

        if (! $this->credits->canSpend($agent, $cost)) {
            throw ValidationException::withMessages(['source' => self::line('studio.errors.credits', ['credits' => (string) $cost])]);
        }

        $language = $agent->locale->value;
        $facts = $source['type'] === 'listing'
            ? $this->generator->facts($agent, ['type' => 'listing', 'id' => $source['id'] ?? null, 'text' => $source['text'] ?? null])
            : ['type' => 'own', 'headline' => self::line('studio.video.headline', [], $language), 'address' => null, 'city' => null, 'price' => null, 'beds' => null, 'baths' => null, 'text' => isset($source['text']) ? trim($source['text']) : null, 'photos' => [], 'listing' => null];
        $media = [...array_map(static fn (string $url): array => ['media' => $url, 'kind' => 'image'], array_slice($facts['photos'], 0, self::PHOTOS)), ...$this->clips($agent, $clips)];

        if ($media === []) {
            throw ValidationException::withMessages(['clips' => self::line('studio.video.errors.media')]);
        }

        // A clip or two after the opening photo, so the video starts on the home.
        $photos = array_values(array_filter($media, static fn (array $m): bool => $m['kind'] === 'image'));
        $videos = array_values(array_filter($media, static fn (array $m): bool => $m['kind'] === 'video'));
        $ordered = $photos === [] ? $videos : [$photos[0], ...$videos, ...array_slice($photos, 1)];
        $lines = $this->lines($facts, $agent, $language);
        $scenes = [];

        $call = array_pop($lines);

        foreach (array_slice($ordered, 0, self::MAX_SCENES - 1) as $i => $m) {
            $length = $m['kind'] === 'video' ? $this->length($m['media']) : null;
            $scenes[] = ['media' => $m['media'], 'kind' => $m['kind'], 'start' => 0.0, 'seconds' => $m['kind'] === 'video' ? min($length ?? 6.0, 8.0) : 3.5, 'length' => $length, 'caption' => $this->clean($agent, $lines[$i] ?? '')];
        }

        // It ends on the agent's card: the brand colour and the call.
        $scenes[] = ['media' => self::CARD, 'kind' => 'image', 'start' => 0.0, 'seconds' => 3.5, 'length' => null, 'caption' => (string) $call];

        $video = StudioVideo::query()->create([
            'uuid' => (string) Str::uuid7(), 'user_id' => $agent->id,
            'source' => ['type' => $source['type'], 'id' => $source['type'] === 'listing' ? ($source['id'] ?? null) : null, 'label' => $facts['address'] ?? Str::limit((string) ($facts['text'] ?? self::line('studio.video.headline', [], $language)), 60)],
            'listing' => $facts['listing'], 'title' => $this->clean($agent, $this->hookTemplates($facts, $language)[0]),
            'scenes' => self::fit($scenes), 'music' => 'calm', 'brand' => true, 'status' => 'rendering',
            'language' => $language, 'engine' => $this->renderer->engine(), 'credits' => $cost,
        ]);
        $this->credits->charge($agent, 'short_video', $this->renderer->engine(), $cost);
        RenderStudioVideo::dispatch($video->id);

        return $video->refresh()->load('user');
    }

    /**
     * Edits from screen 168. The words are checked again; with `render`, the video is rendered again when they pass.
     *
     * @param  array{title?: string, music?: string, brand?: bool, scenes?: list<array{media: string, start?: float|int|string|null, seconds: float|int|string, caption?: string|null}>, fix?: bool, render?: bool}  $edits
     */
    public function update(StudioVideo $video, array $edits): StudioVideo
    {
        if ($video->status === 'rendering') {
            throw ValidationException::withMessages(['video' => self::line('studio.video.errors.busy')]);
        }

        $agent = $video->user;
        $known = array_column($video->scenes, null, 'media');

        if (isset($edits['scenes'])) {
            $scenes = [];

            foreach ($edits['scenes'] as $edit) {
                $was = $known[$edit['media']] ?? $this->clips($agent, [$edit['media']])[0];
                $length = $was['length'] ?? ($was['kind'] === 'video' ? $this->length($was['media']) : null);
                $seconds = max(self::MIN_SCENE, min(self::MAX_SCENE, (float) $edit['seconds']));
                $start = $was['kind'] === 'video' ? max(0.0, (float) ($edit['start'] ?? 0)) : 0.0;

                if ($length !== null) {
                    $start = min($start, max(0.0, $length - self::MIN_SCENE));
                    $seconds = min($seconds, max(self::MIN_SCENE, $length - $start));
                }

                $scenes[] = ['media' => $was['media'], 'kind' => $was['kind'], 'start' => round($start, 1), 'seconds' => round($seconds, 1), 'length' => $length, 'caption' => mb_substr(trim((string) ($edit['caption'] ?? '')), 0, 90)];
            }

            if ($scenes === [] || count($scenes) > self::MAX_SCENES) {
                throw ValidationException::withMessages(['scenes' => self::line('studio.video.errors.scenes', ['max' => (string) self::MAX_SCENES])]);
            }

            if (array_sum(array_column($scenes, 'seconds')) > self::MAX_SECONDS) {
                throw ValidationException::withMessages(['scenes' => self::line('studio.video.errors.long', ['seconds' => (string) self::MAX_SECONDS])]);
            }

            $video->scenes = $scenes;
        }

        if (isset($edits['title'])) {
            $video->title = mb_substr(trim($edits['title']), 0, 80);
        }

        if (isset($edits['music']) && in_array($edits['music'], Music::TRACKS, true)) {
            $video->music = $edits['music'];
        }

        if (isset($edits['brand'])) {
            $video->brand = $edits['brand'];
        }

        if (($edits['fix'] ?? false) === true) {
            $video->title = $this->clean($agent, $video->title);
            $video->scenes = array_map(fn (array $s): array => [...$s, 'caption' => $this->clean($agent, $s['caption'])], $video->scenes);
        }

        $video->save();

        if (($edits['render'] ?? false) === true) {
            if ($this->issues($video) !== []) {
                throw ValidationException::withMessages(['video' => self::line('studio.video.errors.checks')]);
            }

            $video->forceFill(['status' => 'rendering', 'error' => null])->save();
            RenderStudioVideo::dispatch($video->id);
        }

        return $video->refresh()->load('user');
    }

    /**
     * Stronger first words (screen 168, "Hook"; free). Claude when it's on; REOS's templates otherwise.
     *
     * @return list<string>
     */
    public function hooks(StudioVideo $video): array
    {
        $agent = $video->user;
        $facts = $this->facts($video);
        $hooks = $this->hookTemplates($facts, $video->language);
        $bedrock = $this->bedrock->client(20);

        if ($bedrock !== null) {
            try {
                $hooks = (new ClaudeStudioWriter($bedrock['client'], $bedrock['model']))->hooks($facts, $video->title, $video->language);
            } catch (Throwable $e) {
                Log::warning('studio.hooks_failed', ['error' => $e->getMessage()]);
            }
        }

        return array_values(array_unique(array_filter(array_map(fn (string $h): string => mb_substr($this->clean($agent, $h), 0, 80), $hooks))));
    }

    /**
     * Renders the video now (the queue's job). A failure leaves the last good video and says so.
     */
    public function render(StudioVideo $video): void
    {
        $dir = storage_path('app/studio-render/'.$video->uuid.'-'.Str::random(6));
        File::ensureDirectoryExists($dir);

        try {
            $rendered = $this->renderer->render($this->plan($video, $dir), $dir);
            $version = $video->version + 1;
            $base = "social/{$video->user_id}/studio/{$video->uuid}-v{$version}";
            $disk = Storage::disk(SocialPosts::MEDIA_DISK);
            $disk->put("{$base}.mp4", (string) file_get_contents($rendered['video']));
            $disk->put("{$base}.jpg", (string) file_get_contents($rendered['poster']));
            $old = array_filter([$video->path, $video->poster]);
            $video->forceFill(['status' => 'ready', 'version' => $version, 'path' => "{$base}.mp4", 'poster' => "{$base}.jpg", 'seconds' => (string) $rendered['seconds'], 'error' => null, 'engine' => $this->renderer->engine(), 'rendered_at' => now()])->save();
            $disk->delete(array_values($old));
        } catch (Throwable $e) {
            Log::warning('studio.video_failed', ['video' => $video->uuid, 'error' => $e->getMessage()]);
            $this->failed($video);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function failed(StudioVideo $video): void
    {
        $video->forceFill(['status' => 'failed', 'error' => 'studio.video.errors.render'])->save();
    }

    /** @return list<Issue> */
    public function issues(StudioVideo $video): array
    {
        return $this->policy->issues($video->user, implode("\n", [$video->title, ...array_column($video->scenes, 'caption')]));
    }

    /** @return VideoRow */
    public function row(StudioVideo $video): array
    {
        $link = static fn (string $path): string => URL::temporarySignedRoute('social.media', now()->addHours(SocialPosts::MEDIA_HOURS), ['path' => $path]);
        $file = static fn (?string $path): ?array => $path === null ? null : ['path' => $path, 'url' => $link($path)];

        return [
            'id' => $video->uuid,
            'source' => ['type' => $video->source['type'], 'id' => $video->source['id'] ?? null, 'label' => $video->source['label'] ?? null],
            'listing' => $video->listing,
            'title' => $video->title,
            'scenes' => array_map(static fn (array $s): array => [...$s, 'start' => (float) $s['start'], 'seconds' => (float) $s['seconds'], 'length' => $s['length'] === null ? null : (float) $s['length'], 'url' => str_starts_with($s['media'], 'social/') ? $link($s['media']) : ($s['media'] === self::CARD ? '' : $s['media'])], $video->scenes),
            'music' => $video->music,
            'brand' => $video->brand,
            'status' => $video->status,
            'seconds' => $video->seconds === null ? null : (float) $video->seconds,
            'video' => $video->status === 'ready' ? $file($video->path) : null,
            'poster' => $file($video->poster),
            'error' => $video->error === null ? null : self::line($video->error, [], $video->user->locale->value),
            'issues' => $this->issues($video),
            'credits' => $video->credits,
            'engine' => $video->engine,
            'created_at' => $video->created_at?->toIso8601String(),
            'rendered_at' => $video->rendered_at?->toIso8601String(),
        ];
    }

    /**
     * Local files and overlays for the renderer.
     *
     * @return Plan
     */
    private function plan(StudioVideo $video, string $dir): array
    {
        $agent = $video->user;
        $brokerage = $this->policy->brokerage($agent);
        $colour = ($brokerage === null ? null : Brand::of($brokerage)->primary?->hex) ?? StudioGenerator::COLOUR;
        $disk = Storage::disk(SocialPosts::MEDIA_DISK);
        $scenes = [];

        foreach ($video->scenes as $i => $scene) {
            $caption = null;

            if (trim($scene['caption']) !== '') {
                $caption = "{$dir}/caption-{$i}.png";
                VideoFrames::caption($scene['caption'], $caption);
            }

            if ($scene['kind'] === 'image') {
                $file = "{$dir}/still-{$i}.jpg";
                VideoFrames::still($scene['media'] === self::CARD ? null : StudioRenderer::photo($scene['media']), $colour, $file);
                $sound = false;
            } else {
                $file = "{$dir}/clip-{$i}.".pathinfo($scene['media'], PATHINFO_EXTENSION);
                file_put_contents($file, (string) $disk->get($scene['media']));
                $sound = $this->renderer->probe($file)['sound'];
            }

            $scenes[] = ['kind' => $scene['kind'], 'file' => $file, 'start' => (float) $scene['start'], 'seconds' => (float) $scene['seconds'], 'caption' => $caption, 'sound' => $sound];
        }

        VideoFrames::frame($this->attribution($agent), $video->brand ? $colour : VideoFrames::PLAIN, "{$dir}/frame.png");
        $hook = null;

        if (trim($video->title) !== '') {
            $hook = "{$dir}/hook.png";
            VideoFrames::hook($video->title, $hook);
        }

        return ['scenes' => $scenes, 'frame' => "{$dir}/frame.png", 'hook' => $hook, 'hook_seconds' => self::HOOK_SECONDS, 'music' => $video->music];
    }

    /**
     * Scene lengths stretched or squeezed so photos fill about 30 seconds (clips keep theirs).
     *
     * @param  list<Scene>  $scenes
     * @return list<Scene>
     */
    private static function fit(array $scenes): array
    {
        $photos = count(array_filter($scenes, static fn (array $s): bool => $s['kind'] === 'image'));
        $clips = array_sum(array_map(static fn (array $s): float => $s['kind'] === 'video' ? $s['seconds'] : 0.0, $scenes));

        if ($photos === 0) {
            return $scenes;
        }

        $each = round(max(2.5, min(7.5, (self::TARGET_SECONDS - $clips) / $photos)), 1);

        return array_map(static fn (array $s): array => $s['kind'] === 'image' ? [...$s, 'seconds' => $each] : $s, $scenes);
    }

    /**
     * The agent's own uploads, checked to be theirs.
     *
     * @param  list<string>  $paths
     * @return list<array{media: string, kind: 'image'|'video', length?: float|null}>
     */
    private function clips(User $agent, array $paths): array
    {
        $items = [];

        foreach ($paths as $path) {
            if (! str_starts_with($path, "social/{$agent->id}/") || str_contains($path, '..') || ! Storage::disk(SocialPosts::MEDIA_DISK)->exists($path)) {
                throw ValidationException::withMessages(['clips' => self::line('social.errors.media')]);
            }

            $kind = preg_match('/\.(mp4|mov|m4v)$/i', $path) === 1 ? 'video' : (preg_match('/\.(jpe?g|png|webp)$/i', $path) === 1 ? 'image' : null);
            $items[] = $kind !== null ? ['media' => $path, 'kind' => $kind] : throw ValidationException::withMessages(['clips' => self::line('social.errors.media')]);
        }

        return $items;
    }

    private function length(string $path): ?float
    {
        $file = tempnam(sys_get_temp_dir(), 'reos-clip');

        try {
            file_put_contents($file, (string) Storage::disk(SocialPosts::MEDIA_DISK)->get($path));
            $seconds = $this->renderer->probe($file)['seconds'];

            return $seconds === null ? null : round($seconds, 1);
        } finally {
            @unlink($file);
        }
    }

    /**
     * A caption for each scene, in order: where, what, how much, then the call.
     *
     * @param  array<string, mixed>  $facts
     * @return list<string>
     */
    private function lines(array $facts, User $agent, string $language): array
    {
        $beds = is_string($facts['beds'] ?? null) ? self::line('studio.beds', ['beds' => $facts['beds'], 'baths' => is_string($facts['baths'] ?? null) ? $facts['baths'] : '—'], $language) : null;
        $text = is_string($facts['text'] ?? null) && $facts['text'] !== '' ? Str::limit($facts['text'], 80) : null;

        return array_values(array_filter([
            is_string($facts['address'] ?? null) ? $facts['address'] : $text,
            $beds,
            is_string($facts['price'] ?? null) ? $facts['price'] : null,
            is_string($facts['address'] ?? null) ? $text : null,
            self::line('studio.video.cta', ['name' => (string) $agent->name], $language),
        ]));
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return non-empty-list<string>
     */
    private function hookTemplates(array $facts, string $language): array
    {
        $type = ($facts['type'] ?? null) === 'listing' ? 'listing' : 'own';
        $replace = ['city' => is_string($facts['city'] ?? null) ? $facts['city'] : 'Texas', 'beds' => is_string($facts['beds'] ?? null) ? $facts['beds'] : ''];

        return [
            self::line("studio.video.hooks.{$type}.1", $replace, $language),
            self::line("studio.video.hooks.{$type}.2", $replace, $language),
            self::line("studio.video.hooks.{$type}.3", $replace, $language),
        ];
    }

    /** @return array<string, mixed> */
    private function facts(StudioVideo $video): array
    {
        if ($video->source['type'] === 'listing' && $video->source['id'] !== null) {
            try {
                return $this->generator->facts($video->user, ['type' => 'listing', 'id' => $video->source['id']]);
            } catch (Throwable) {
                // The listing is gone or can't be posted any more: hooks from the video's own words.
            }
        }

        return ['type' => 'own', 'headline' => $video->title, 'address' => $video->source['label'] ?? null, 'city' => null, 'price' => null, 'beds' => null, 'baths' => null, 'text' => implode(' ', array_column($video->scenes, 'caption')), 'photos' => [], 'listing' => null];
    }

    private function attribution(User $agent): string
    {
        $brokerage = $this->policy->brokerage($agent);

        return trim((string) $agent->name).($brokerage !== null ? ' · '.$brokerage->name : '');
    }

    private function clean(User $agent, string $text): string
    {
        return $this->policy->fix($agent, $text);
    }

    /** @param  array<string, string>  $replace */
    private static function line(string $key, array $replace = [], ?string $language = null): string
    {
        $line = __($key, $replace, $language);

        return is_string($line) ? $line : $key;
    }
}
