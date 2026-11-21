<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Connections\ConnectorDriver;
use App\Connections\Exceptions\AccessRevoked;
use App\Models\Connection;

/** A working access token for a connection, refreshed a minute before it expires. */
final readonly class ConnectionTokens
{
    /** @throws AccessRevoked */
    public function accessToken(Connection $connection, ConnectorDriver $driver, bool $forceRefresh = false): string
    {
        $expiresSoon = $connection->token_expires_at !== null && $connection->token_expires_at->lte(now()->addMinute());

        if (! $forceRefresh && ! $expiresSoon && $connection->access_token !== null) {
            return $connection->access_token;
        }

        if ($connection->refresh_token === null) {
            throw new AccessRevoked('No refresh token.');
        }

        $tokens = $driver->refresh($connection->refresh_token);
        $connection->forceFill([
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken ?? $connection->refresh_token,
            'token_expires_at' => $tokens->expiresAt,
        ])->save();

        return $tokens->accessToken;
    }
}
