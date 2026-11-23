<?php

declare(strict_types=1);

namespace App\Content\Avatars;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Synthesia (A7.3): POST /v2/videos with the script, avatar and background
 * (Settings › Integrations), then GET /v2/videos/{id} until it's complete.
 * With test keys the videos are Synthesia's free, watermarked test videos.
 * Confirm the fields against the sandbox before Live.
 */
final readonly class SynthesiaVideo implements AvatarVideo
{
    private const BASE = 'https://api.synthesia.io/v2';

    /** Synthesia's script languages for REOS's two voices. */
    private const LANGUAGES = ['en' => 'en-US', 'es' => 'es-MX'];

    /** @param  array<string, string>  $keys */
    public function __construct(private array $keys, private bool $test) {}

    public function vendor(): string
    {
        return 'synthesia';
    }

    public function simulated(): bool
    {
        return false;
    }

    public function stockAvatar(): string
    {
        return $this->keys['stock_avatar'] ?? '';
    }

    public function start(string $avatar, string $language, string $script, string $title, string $reference): string
    {
        if ($avatar === '') {
            throw new RuntimeException('Synthesia needs an avatar.');
        }

        $id = $this->http()->post(self::BASE.'/videos', [
            'test' => $this->test,
            'title' => mb_substr($title, 0, 100),
            'visibility' => 'private',
            'callbackId' => $reference,
            'input' => [[
                'scriptText' => $script,
                'scriptLanguage' => self::LANGUAGES[$language] ?? 'en-US',
                'avatar' => $avatar,
                'background' => ($this->keys['background'] ?? '') !== '' ? $this->keys['background'] : 'off_white',
            ]],
        ])->throw()->json('id');

        return is_string($id) && $id !== '' ? $id : throw new RuntimeException('Synthesia returned no video id.');
    }

    public function check(string $id): array
    {
        $data = $this->http()->get(self::BASE.'/videos/'.rawurlencode($id))->throw()->json();
        $data = is_array($data) ? $data : [];
        $status = $data['status'] ?? null;

        return [
            'status' => match ($status) {
                'complete' => 'ready',
                'error', 'rejected' => 'failed',
                default => 'rendering',
            },
            'url' => is_string($data['download'] ?? null) ? $data['download'] : null,
            'seconds' => self::seconds($data['duration'] ?? null),
            'error' => in_array($status, ['error', 'rejected'], true) ? 'Synthesia: '.$status : null,
        ];
    }

    public function delete(string $id): void
    {
        $this->http()->delete(self::BASE.'/videos/'.rawurlencode($id))->throw();
    }

    /** Synthesia gives the length as seconds or as "0:01:31.2". */
    private static function seconds(mixed $duration): ?int
    {
        if (is_numeric($duration)) {
            return (int) ceil((float) $duration);
        }

        if (is_string($duration) && preg_match('/^(\d+):(\d{2}):(\d{2}(?:\.\d+)?)$/', $duration, $m) === 1) {
            return (int) ceil((int) $m[1] * 3600 + (int) $m[2] * 60 + (float) $m[3]);
        }

        return null;
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(['Authorization' => $this->keys['api_key'] ?? '', 'Accept' => 'application/json'])->timeout(30)->connectTimeout(5)->retry(2, 500, throw: false);
    }
}
