<?php

declare(strict_types=1);

namespace App\Connections;

use App\Models\Connection;

/**
 * A social channel (B11, ADR 0046): where a connected account can post,
 * publishing one post there, and reading its insights back. Instagram and
 * Facebook (Meta), LinkedIn, Google Business Profile, YouTube and TikTok
 * each implement it, with a Simulator for demo data.
 *
 * @phpstan-type Target array{channel: string, id: string, name: string}
 * @phpstan-type Content array{text: string, link: string|null, media: list<array{url: string, kind: string}>}
 */
interface PublishesPosts
{
    /**
     * The places this account can post: pages, profiles, channels.
     *
     * @return list<Target>
     */
    public function targets(Connection $connection, string $accessToken): array;

    /**
     * Publish now.
     *
     * @param  Target  $target
     * @param  Content  $content
     * @return array{id: string, url: string|null}
     */
    public function publish(Connection $connection, string $accessToken, array $target, array $content): array;

    /**
     * What the channel reports for a post: any of reach, views, likes, comments, clicks, shares.
     *
     * @param  Target  $target
     * @return array<string, int>
     */
    public function insights(Connection $connection, string $accessToken, array $target, string $externalId): array;

    /**
     * What each channel needs from a post: whether it needs an image or video, and the longest text.
     *
     * @return array<string, array{media: 'required'|'optional'|'video', max_text: int}>
     */
    public static function channels(): array;
}
