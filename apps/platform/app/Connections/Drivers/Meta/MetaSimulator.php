<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Meta;

use App\Connections\Drivers\Social\SocialSimulator;
use App\Models\Connection;

/** Instagram and Facebook on demo data (B11.1): one Facebook Page with its Instagram account. */
final class MetaSimulator extends SocialSimulator
{
    public const PAGE = 'sim-page-1';

    public const INSTAGRAM = 'sim-ig-1';

    public function integration(): string
    {
        return MetaDriver::INTEGRATION;
    }

    public static function channels(): array
    {
        return MetaDriver::channels();
    }

    protected function label(): string
    {
        return 'James Miller (demo)';
    }

    protected function places(): array
    {
        return [
            ['channel' => 'facebook', 'id' => self::PAGE, 'name' => 'James Miller, REALTOR® (demo Page)'],
            ['channel' => 'instagram', 'id' => self::INSTAGRAM, 'name' => '@jamesmiller.homes (demo)'],
        ];
    }

    /** @return array<string, float> */
    protected function metrics(): array
    {
        return ['reach' => 1.0, 'likes' => 1 / 12, 'comments' => 1 / 60, 'clicks' => 1 / 25];
    }

    /** Instagram reports views rather than link clicks. */
    public function insights(Connection $connection, string $accessToken, array $target, string $externalId): array
    {
        $all = parent::insights($connection, $accessToken, $target, $externalId);

        if ($target['channel'] === 'instagram') {
            unset($all['clicks']);
            $all['views'] = (int) ($all['reach'] * 1.4);
        }

        return $all;
    }
}
