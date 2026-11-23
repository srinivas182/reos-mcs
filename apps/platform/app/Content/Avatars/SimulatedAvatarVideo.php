<?php

declare(strict_types=1);

namespace App\Content\Avatars;

use Illuminate\Support\Str;

/** The avatar vendor when none is set up: every video is ready at once, as a short placeholder (A7.3). */
final class SimulatedAvatarVideo implements AvatarVideo
{
    /** The placeholder REOS copies in place of a rendered video. */
    public static function placeholder(): string
    {
        return resource_path('content/simulated-lesson.mp4');
    }

    public function vendor(): string
    {
        return 'simulated';
    }

    public function simulated(): bool
    {
        return true;
    }

    public function stockAvatar(): string
    {
        return 'simulated-presenter';
    }

    public function start(string $avatar, string $language, string $script, string $title, string $reference): string
    {
        return 'sim-'.Str::uuid7()->toString();
    }

    public function check(string $id): array
    {
        return ['status' => 'ready', 'url' => null, 'seconds' => null, 'error' => null];
    }

    public function delete(string $id): void {}
}
