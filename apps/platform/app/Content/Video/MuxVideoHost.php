<?php

declare(strict_types=1);

namespace App\Content\Video;

use App\Models\LessonMedia;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * Mux Video (A5.3). Videos are copied from S3 with a short-lived signed link,
 * play with signed playback (never a public link), and Mux writes captions
 * in the lesson's language. Mux says when an asset and its captions are
 * ready through the signed webhook (MuxWebhooks).
 */
final readonly class MuxVideoHost implements VideoHost
{
    public const API = 'https://api.mux.com/video/v1';

    public const STREAM = 'https://stream.mux.com';

    /** The download's resolution: about 180 MB an hour (A8.2b, decision 1). */
    public const OFFLINE = '720p';

    private const LANGUAGES = ['en' => 'English', 'es' => 'Español'];

    public function __construct(private Http $http, private MuxKeys $keys) {}

    public function start(LessonMedia $media, string $sourceUrl, string $language): void
    {
        $response = $this->api()->post('/assets', [
            'inputs' => [[
                'url' => $sourceUrl,
                'generated_subtitles' => [['language_code' => $language, 'name' => self::LANGUAGES[$language] ?? $language]],
            ]],
            'playback_policies' => ['signed'],
            'video_quality' => 'basic',
            // A 720p MP4 for offline lessons on the phone (A8.2b, decision 1).
            'static_renditions' => [['resolution' => self::OFFLINE]],
            'passthrough' => 'lesson_media:'.$media->id,
        ]);

        $id = $response->json('data.id');

        if (! $response->successful() || ! is_string($id)) {
            throw new RuntimeException('Mux did not accept the video (HTTP '.$response->status().').');
        }

        $playback = $response->json('data.playback_ids.0.id');
        $media->forceFill(['mux_asset_id' => $id, 'mux_playback_id' => is_string($playback) ? $playback : null])->save();
    }

    public function remove(LessonMedia $media): void
    {
        if ($media->mux_asset_id !== null) {
            $this->api()->delete('/assets/'.rawurlencode($media->mux_asset_id));
        }
    }

    /** The signed link to the 720p download, for a phone saving the lesson. */
    public function downloadUrl(string $playbackId, int $minutes = 60): string
    {
        return self::STREAM."/{$playbackId}/".self::OFFLINE.'.mp4?token='.$this->token($playbackId, 'v', $minutes);
    }

    /** A captions file (WebVTT) Mux generated, fetched with a signed token. */
    public function captions(string $playbackId, string $trackId): ?string
    {
        $response = $this->http->timeout(20)->get(self::STREAM."/{$playbackId}/text/{$trackId}.vtt", ['token' => $this->token($playbackId, 'v')]);

        return $response->successful() ? $response->body() : null;
    }

    /**
     * A signed playback token (RS256, the signing key from Settings ›
     * Integrations): `v` for video, `t` for thumbnails. Short-lived.
     */
    /**
     * A signed playback token. Playback modifiers such as `max_resolution`
     * go in the token, not the URL (Mux signed playback).
     *
     * @param  array<string, string>  $modifiers
     */
    public function token(string $playbackId, string $audience, int $minutes = 360, array $modifiers = []): string
    {
        return JWT::encode(
            [...$modifiers, 'sub' => $playbackId, 'aud' => $audience, 'exp' => now()->addMinutes($minutes)->getTimestamp()],
            $this->keys->privateKeyPem(),
            'RS256',
            $this->keys->signingKeyId,
        );
    }

    private function api(): PendingRequest
    {
        return $this->http->baseUrl(self::API)->withBasicAuth($this->keys->tokenId, $this->keys->secret)->acceptJson()->timeout(20)->retry(2, 500, throw: false);
    }
}
