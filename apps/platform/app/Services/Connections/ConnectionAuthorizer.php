<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Connections\ConnectionStatus;
use App\Connections\ConnectorRegistry;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\HistoryRange;
use App\Connections\SyncTrigger;
use App\Integrations\IntegrationMode;
use App\Jobs\SyncConnection;
use App\Models\Connection;
use App\Models\OAuthAuthorization;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vendor sign-in (OAuth 2 authorisation code with PKCE) for connections.
 *
 * - `start` saves a single-use authorisation (verifier, owner, where to
 *   return) and hands back the tool's sign-in address. The `state` is its id
 *   plus an HMAC, so made-up states are refused without a lookup.
 * - `complete` checks the state (genuine, unused, under ten minutes, same
 *   tool), swaps the code using the verifier, saves the connection and
 *   queues the first sync.
 */
final readonly class ConnectionAuthorizer
{
    public const LIFETIME_MINUTES = 10;

    public const OUTCOME_CONNECTED = 'connected';

    public const OUTCOME_CANCELLED = 'cancelled';

    public const OUTCOME_FAILED = 'failed';

    public function __construct(
        private ConnectorRegistry $registry,
        private AuditLogger $audit,
    ) {}

    /** The tool's own sign-in callback address, as shown in Settings › Integrations. */
    public static function redirectUri(string $integrationId): string
    {
        return url("integrations/{$integrationId}/callback");
    }

    public function start(User $user, string $integrationId, string $ownerType, int $ownerId, string $returnTo, HistoryRange $history): string
    {
        $mode = $this->registry->mode($integrationId);
        $verifier = Str::random(64);

        $authorization = OAuthAuthorization::query()->create([
            'integration_id' => $integrationId,
            'user_id' => $user->id,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'mode' => $mode,
            'code_verifier' => $verifier,
            'return_to' => $returnTo,
            'settings' => ['history' => $history->value],
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        return $this->registry->driver($integrationId, $mode)->authorizationUrl(
            self::state($authorization->id),
            self::challenge($verifier),
            self::redirectUri($integrationId),
        );
    }

    /** The authorisation a state belongs to, if it's genuine (used or not). */
    public function find(string $state): ?OAuthAuthorization
    {
        $id = self::idFrom($state);

        return $id === null ? null : OAuthAuthorization::query()->find($id);
    }

    /**
     * @return array{outcome: string, authorization: OAuthAuthorization|null, connection: Connection|null, reason: string|null}
     */
    public function complete(string $integrationId, string $state, ?string $code, ?string $error): array
    {
        $authorization = DB::transaction(function () use ($state, $integrationId): ?OAuthAuthorization {
            $id = self::idFrom($state);
            $authorization = $id === null ? null : OAuthAuthorization::query()->lockForUpdate()->find($id);

            if ($authorization === null || $authorization->used_at !== null || $authorization->expires_at->isPast() || $authorization->integration_id !== $integrationId) {
                return null;
            }

            $authorization->forceFill(['used_at' => now()])->save();

            return $authorization;
        });

        if ($authorization === null) {
            return ['outcome' => self::OUTCOME_FAILED, 'authorization' => null, 'connection' => null, 'reason' => 'connections.errors.stateInvalid'];
        }

        if ($error !== null || $code === null || $code === '') {
            return ['outcome' => self::OUTCOME_CANCELLED, 'authorization' => $authorization, 'connection' => null, 'reason' => 'connections.errors.cancelled'];
        }

        // The super admin switched the tool's run mode during the sign-in.
        if ($this->registry->mode($integrationId) !== $authorization->mode || $authorization->mode === IntegrationMode::Off) {
            return ['outcome' => self::OUTCOME_FAILED, 'authorization' => $authorization, 'connection' => null, 'reason' => 'connections.errors.unavailable'];
        }

        $driver = $this->registry->driver($integrationId, $authorization->mode);

        try {
            $tokens = $driver->exchangeCode($code, $authorization->code_verifier, self::redirectUri($integrationId));
            $account = $driver->account($tokens);
        } catch (AccessRevoked) {
            return ['outcome' => self::OUTCOME_FAILED, 'authorization' => $authorization, 'connection' => null, 'reason' => 'connections.errors.refused'];
        } catch (VendorUnavailable|VendorRateLimited) {
            return ['outcome' => self::OUTCOME_FAILED, 'authorization' => $authorization, 'connection' => null, 'reason' => 'connections.errors.vendorUnavailable'];
        }

        $connection = Connection::query()->firstOrNew([
            'integration_id' => $integrationId,
            'owner_type' => $authorization->owner_type,
            'owner_id' => $authorization->owner_id,
            'external_account_id' => $account->externalId,
        ]);
        $reconnected = $connection->exists;

        $connection->fill([
            'connected_by' => $authorization->user_id,
            'mode' => $authorization->mode,
            'account_label' => $account->label,
            'scopes' => $tokens->scopes,
            'status' => ConnectionStatus::Active,
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken ?? $connection->refresh_token,
            'token_expires_at' => $tokens->expiresAt,
        ]);
        if (! $reconnected) {
            $connection->settings = $authorization->settings;
        }
        $connection->forceFill(['consecutive_failures' => 0, 'last_error' => null])->save();

        $this->audit->record($reconnected ? 'connection.reconnected' : 'connection.connected', 'connection', (string) $connection->id, [
            'integration' => $integrationId,
            'owner' => $connection->ownerKey(),
            'mode' => $authorization->mode->value,
        ], $authorization->user);

        foreach (array_keys($driver->streams()) as $stream) {
            SyncConnection::dispatch($connection->id, $stream, $reconnected ? SyncTrigger::Manual : SyncTrigger::First);
        }

        return ['outcome' => self::OUTCOME_CONNECTED, 'authorization' => $authorization, 'connection' => $connection, 'reason' => null];
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private static function state(string $id): string
    {
        return $id.'.'.self::sign($id);
    }

    private static function idFrom(string $state): ?string
    {
        $parts = explode('.', $state, 2);

        return count($parts) === 2 && Str::isUuid($parts[0]) && hash_equals(self::sign($parts[0]), $parts[1]) ? $parts[0] : null;
    }

    private static function sign(string $id): string
    {
        return substr(hash_hmac('sha256', "oauth-state:{$id}", config()->string('app.key')), 0, 32);
    }
}
