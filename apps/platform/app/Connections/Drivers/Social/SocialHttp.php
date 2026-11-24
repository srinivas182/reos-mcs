<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Social;

use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use Illuminate\Http\Client\Response;

/** How social channels' answers become REOS's errors (B11): 401 revoked, 429 rate-limited, the rest unavailable. */
trait SocialHttp
{
    /** @return array<mixed> */
    private static function body(Response $response, string $vendor): array
    {
        if ($response->status() === 429) {
            throw new VendorRateLimited(max(30, (int) $response->header('Retry-After')));
        }

        if ($response->status() === 401) {
            throw new AccessRevoked("{$vendor}: access revoked.");
        }

        if ($response->failed()) {
            $message = $response->json('message') ?? $response->json('error.message') ?? $response->body();

            throw new VendorUnavailable("{$vendor}: ".mb_substr(is_string($message) ? $message : '', 0, 200));
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }
}
