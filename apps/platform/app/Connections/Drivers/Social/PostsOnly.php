<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Social;

use App\Connections\SyncPage;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * For social channels (B11, ADR 0046): nothing syncs and no webhooks come
 * in; posts go out from REOS and insights are read per post.
 */
trait PostsOnly
{
    public function streams(): array
    {
        return [];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        return new SyncPage([], [], null, true);
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        return null;
    }
}
