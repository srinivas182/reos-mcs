<?php

declare(strict_types=1);

namespace App\Connections\Drivers\YouTube;

use App\Connections\Drivers\Social\SocialSimulator;

/** YouTube Shorts on demo data (B11.3): the agent's channel. */
final class YouTubeSimulator extends SocialSimulator
{
    public function integration(): string
    {
        return YouTubeDriver::INTEGRATION;
    }

    public static function channels(): array
    {
        return YouTubeDriver::channels();
    }

    protected function label(): string
    {
        return 'james@reos.example (demo)';
    }

    protected function places(): array
    {
        return [['channel' => 'youtube', 'id' => 'sim-yt-channel', 'name' => 'James Miller Homes (demo channel)']];
    }

    protected function metrics(): array
    {
        return ['views' => 1.0, 'likes' => 1 / 25, 'comments' => 1 / 150];
    }
}
