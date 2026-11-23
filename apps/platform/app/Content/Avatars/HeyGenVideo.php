<?php

declare(strict_types=1);

namespace App\Content\Avatars;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HeyGen (A7.3): POST /v2/video/generate with the avatar and the voice for
 * the language (Settings › Integrations), then the video's status until
 * it's completed. Confirm the fields against the sandbox before Live
 * (register note: HeyGen also has a newer v3 API).
 */
final readonly class HeyGenVideo implements AvatarVideo
{
    private const BASE = 'https://api.heygen.com';

    /** @param  array<string, string>  $keys */
    public function __construct(private array $keys) {}

    public function vendor(): string
    {
        return 'heygen';
    }

    public function simulated(): bool
    {
        return false;
    }

    public function stockAvatar(): string
    {
        return $this->keys['stock_avatar_id'] ?? '';
    }

    public function start(string $avatar, string $language, string $script, string $title, string $reference): string
    {
        $voice = $this->keys['voice_id_'.$language] ?? '';

        if ($avatar === '' || $voice === '') {
            throw new RuntimeException('HeyGen needs an avatar id and a voice id for '.$language.'.');
        }

        $id = $this->http()->post(self::BASE.'/v2/video/generate', [
            'title' => mb_substr($title, 0, 100),
            'callback_id' => $reference,
            'caption' => false,
            'dimension' => ['width' => 1280, 'height' => 720],
            'video_inputs' => [[
                'character' => ['type' => 'avatar', 'avatar_id' => $avatar, 'avatar_style' => 'normal'],
                'voice' => ['type' => 'text', 'voice_id' => $voice, 'input_text' => $script],
                'background' => ['type' => 'color', 'value' => '#F6F3EE'],
            ]],
        ])->throw()->json('data.video_id');

        return is_string($id) && $id !== '' ? $id : throw new RuntimeException('HeyGen returned no video id.');
    }

    public function check(string $id): array
    {
        $data = $this->http()->get(self::BASE.'/v1/video_status.get', ['video_id' => $id])->throw()->json('data');
        $data = is_array($data) ? $data : [];
        $status = $data['status'] ?? null;
        $duration = $data['duration'] ?? null;
        $error = $data['error'] ?? null;

        return [
            'status' => match ($status) {
                'completed' => 'ready',
                'failed' => 'failed',
                default => 'rendering',
            },
            'url' => is_string($data['video_url'] ?? null) ? $data['video_url'] : null,
            'seconds' => is_numeric($duration) ? (int) ceil((float) $duration) : null,
            'error' => is_string($error) ? mb_substr($error, 0, 200) : (is_array($error) && is_string($error['message'] ?? null) ? mb_substr($error['message'], 0, 200) : null),
        ];
    }

    public function delete(string $id): void
    {
        $this->http()->delete(self::BASE.'/v1/video.delete?video_id='.rawurlencode($id))->throw();
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(['x-api-key' => $this->keys['api_key'] ?? '', 'Accept' => 'application/json'])->timeout(30)->connectTimeout(5)->retry(2, 500, throw: false);
    }
}
