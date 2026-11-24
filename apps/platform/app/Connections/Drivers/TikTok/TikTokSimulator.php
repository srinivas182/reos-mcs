<?php

declare(strict_types=1);

namespace App\Connections\Drivers\TikTok;

use App\Connections\Drivers\Social\SocialSimulator;

/** TikTok on demo data (B11.3): the agent's account, posting privately as before the audit. */
final class TikTokSimulator extends SocialSimulator
{
    public function integration(): string
    {
        return TikTokDriver::INTEGRATION;
    }

    public static function channels(): array
    {
        return TikTokDriver::channels();
    }

    protected function label(): string
    {
        return '@jamesmiller.homes (demo)';
    }

    protected function places(): array
    {
        return [['channel' => 'tiktok', 'id' => 'sim-tt-1', 'name' => '@jamesmiller.homes (demo, private until audit)']];
    }

    protected function metrics(): array
    {
        return ['views' => 1.0, 'likes' => 1 / 10, 'comments' => 1 / 90, 'shares' => 1 / 120];
    }
}
