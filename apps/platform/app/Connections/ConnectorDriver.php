<?php

declare(strict_types=1);

namespace App\Connections;

use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\CursorExpired;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * What every connectable tool implements (ADR 0013). Each tool has a real
 * driver (sandbox and live keys) and a Simulator (demo data), both behind
 * this contract, so features never know which one they talk to.
 *
 * Drivers only talk to the tool; saving, retries, health and fairness are the
 * engine's job.
 */
interface ConnectorDriver
{
    /** The register id this driver serves (for example `google-calendar`). */
    public function integration(): string;

    /**
     * Where to send the person to sign in at the tool.
     *
     * @param  string  $codeChallenge  PKCE S256 challenge.
     */
    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string;

    /**
     * Swap the code from the callback for tokens.
     *
     * @throws AccessRevoked when the tool refuses the code.
     */
    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet;

    /** @throws AccessRevoked when the refresh token no longer works. */
    public function refresh(string $refreshToken): TokenSet;

    /** Best effort: tell the tool to forget REOS's access. */
    public function revoke(string $token): void;

    public function account(TokenSet $tokens): ConnectedAccount;

    /**
     * The data streams this tool syncs, each with the store that saves it.
     *
     * @return array<string, class-string<StreamStore>>
     */
    public function streams(): array;

    /**
     * One page of a stream. A null cursor starts from `$since` (null: everything).
     *
     * @throws AccessRevoked|VendorRateLimited|VendorUnavailable|CursorExpired
     */
    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage;

    /** Requests per minute the tool allows per REOS client (shared by every connection). */
    public function requestsPerMinute(): int;

    /**
     * Check an inbound webhook's signature and say which connections changed.
     * Returns null when the request isn't genuine.
     */
    public function verifyWebhook(Request $request): ?WebhookDelivery;
}
