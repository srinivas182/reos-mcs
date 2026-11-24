<?php

declare(strict_types=1);

namespace App\Connections\Drivers\LinkedIn;

use App\Connections\Drivers\Social\SocialSimulator;

/** LinkedIn on demo data (B11.2): the member's profile and one company page. */
final class LinkedInSimulator extends SocialSimulator
{
    public function integration(): string
    {
        return LinkedInDriver::INTEGRATION;
    }

    public static function channels(): array
    {
        return LinkedInDriver::channels();
    }

    protected function label(): string
    {
        return 'James Miller (demo)';
    }

    protected function places(): array
    {
        return [
            ['channel' => 'linkedin', 'id' => 'urn:li:person:sim-1', 'name' => 'James Miller (demo profile)'],
            ['channel' => 'linkedin', 'id' => 'urn:li:organization:sim-2', 'name' => 'Lone Star Realty (demo page)'],
        ];
    }

    protected function metrics(): array
    {
        return ['reach' => 1.0, 'likes' => 1 / 20, 'comments' => 1 / 80, 'clicks' => 1 / 30];
    }
}
