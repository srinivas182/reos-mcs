<?php

declare(strict_types=1);

namespace App\Content\Video;

use App\Content\MediaStatus;
use App\Jobs\FetchVideoCaptions;
use App\Jobs\IngestLesson;
use App\Models\LessonMedia;
use App\Models\WebhookReceipt;

/**
 * Mux's webhook (A5.3): signed with the webhook secret (`Mux-Signature:
 * t=<time>,v1=<HMAC-SHA256 of "<time>.<body>">`, at most five minutes old),
 * each event handled once. A ready video starts playing from Mux; ready
 * captions are fetched and become searchable passages.
 */
final readonly class MuxWebhooks
{
    public const ACCEPTED = 'accepted';

    public const DUPLICATE = 'duplicate';

    public const REFUSED = 'refused';

    private const TOLERANCE_SECONDS = 300;

    public function __construct(private VideoHosts $videos) {}

    public function receive(string $body, ?string $signature): string
    {
        $keys = $this->videos->keys();

        if ($keys === null || $keys->webhookSecret === '' || ! self::verify($body, (string) $signature, $keys->webhookSecret, time())) {
            return self::REFUSED;
        }

        $event = json_decode($body, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)) {
            return self::REFUSED;
        }

        $inserted = WebhookReceipt::query()->insertOrIgnore([
            'integration_id' => VideoHosts::INTEGRATION,
            'delivery_key' => mb_substr($event['id'], 0, 255),
            'received_at' => now(),
        ]);

        if ($inserted === 0) {
            return self::DUPLICATE;
        }

        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        $this->handle($event['type'], $data);

        return self::ACCEPTED;
    }

    public static function verify(string $body, string $header, string $secret, int $now): bool
    {
        $time = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $time = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($time === null || abs($now - $time) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $time.'.'.$body, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<mixed>  $data */
    private function handle(string $type, array $data): void
    {
        $assetId = match ($type) {
            'video.asset.track.ready' => $data['asset_id'] ?? null,
            default => $data['id'] ?? null,
        };

        $media = is_string($assetId) ? LessonMedia::query()->where('mux_asset_id', $assetId)->first() : null;

        if ($media === null) {
            return;
        }

        if ($type === 'video.asset.ready') {
            $ids = is_array($data['playback_ids'] ?? null) ? $data['playback_ids'] : [];
            $first = is_array($ids[0] ?? null) ? $ids[0] : [];
            $playback = $first['id'] ?? null;
            $duration = $data['duration'] ?? null;
            $media->forceFill([
                'status' => MediaStatus::Ready,
                'failure' => null,
                'mux_playback_id' => is_string($playback) ? $playback : $media->mux_playback_id,
                'duration_seconds' => is_numeric($duration) ? (int) round((float) $duration) : $media->duration_seconds,
            ])->save();
            IngestLesson::dispatch($media->lesson_id);
        } elseif ($type === 'video.asset.errored') {
            $media->forceFill(['status' => MediaStatus::Failed, 'failure' => 'content.files.failed.video'])->save();
        } elseif ($type === 'video.asset.track.ready' && ($data['type'] ?? null) === 'text' && is_string($data['id'] ?? null)) {
            FetchVideoCaptions::dispatch($media->id, $data['id']);
        }
    }
}
