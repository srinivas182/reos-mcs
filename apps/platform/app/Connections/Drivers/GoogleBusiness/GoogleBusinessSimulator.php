<?php

declare(strict_types=1);

namespace App\Connections\Drivers\GoogleBusiness;

use App\Connections\Drivers\Social\SocialSimulator;

/** Google Business Profile on demo data (B11.2): one business location. */
final class GoogleBusinessSimulator extends SocialSimulator
{
    public function integration(): string
    {
        return GoogleBusinessDriver::INTEGRATION;
    }

    public static function channels(): array
    {
        return GoogleBusinessDriver::channels();
    }

    protected function label(): string
    {
        return 'james@reos.example (demo)';
    }

    protected function places(): array
    {
        return [['channel' => 'google_business', 'id' => 'accounts/sim-1/locations/sim-2', 'name' => 'James Miller, REALTOR® – Austin (demo)']];
    }

    protected function metrics(): array
    {
        return ['views' => 1.0, 'clicks' => 1 / 30];
    }
}
