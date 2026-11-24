<?php

declare(strict_types=1);

namespace App\Social;

use App\Connections\ConnectionStatus;
use App\Connections\ConnectorDriver;
use App\Connections\ConnectorRegistry;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\PublishesPosts;
use App\Integrations\Catalogue;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\User;
use App\Services\Connections\ConnectionTokens;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Social accounts (B11, ADR 0046; agent screen 170): every social channel
 * in the integrations register, whether the agent has connected it, and
 * where each connection can post (pages, profiles, channels). The places
 * are read from the channel and kept on the connection for a day.
 *
 * @phpstan-type Target array{channel: string, id: string, name: string}
 * @phpstan-type Account array{integration: string, name: string, purpose: string, launch: string, built: bool, available: bool, connection: array{id: int, status: string, label: string|null}|null, targets: list<Target>, channels: array<string, array{media: string, max_text: int}>}
 */
final readonly class SocialAccounts
{
    private const KEEP_HOURS = 24;

    public function __construct(
        private Catalogue $catalogue,
        private ConnectorRegistry $registry,
        private ConnectionTokens $tokens,
    ) {}

    /** @return list<Account> */
    public function for(User $user): array
    {
        $accounts = [];

        foreach ($this->catalogue->all() as $definition) {
            if ($definition->category !== 'social') {
                continue;
            }

            $built = ConnectorRegistry::isSocial($definition->id);
            $connection = $built ? $this->connection($user, $definition->id) : null;
            $driver = $connection === null ? null : $this->driver($connection);

            $accounts[] = [
                'integration' => $definition->id,
                'name' => $definition->name,
                'purpose' => $definition->purpose,
                'launch' => $definition->launch,
                'built' => $built,
                'available' => $built && $this->registry->mode($definition->id) !== IntegrationMode::Off,
                'connection' => $connection === null ? null : ['id' => $connection->id, 'status' => $connection->status->value, 'label' => $connection->account_label],
                'targets' => $connection === null ? [] : $this->targets($connection),
                'channels' => $driver === null ? [] : $driver::channels(),
            ];
        }

        return $accounts;
    }

    /**
     * Where a connection can post, read again after a day or when asked.
     *
     * @return list<Target>
     */
    public function targets(Connection $connection, bool $fresh = false): array
    {
        $kept = $connection->meta['social_targets'] ?? null;
        $at = $connection->meta['social_targets_at'] ?? null;

        if (! $fresh && is_array($kept) && is_string($at) && CarbonImmutable::parse($at)->gt(CarbonImmutable::now()->subHours(self::KEEP_HOURS))) {
            return self::clean($kept);
        }

        $driver = $this->driver($connection);

        if ($driver === null || $connection->status === ConnectionStatus::ReconnectNeeded) {
            return is_array($kept) ? self::clean($kept) : [];
        }

        try {
            $targets = $driver->targets($connection, $this->tokens->accessToken($connection, $driver));
        } catch (AccessRevoked) {
            $connection->forceFill(['status' => ConnectionStatus::ReconnectNeeded])->save();

            return [];
        } catch (Throwable $e) {
            report($e);

            return is_array($kept) ? self::clean($kept) : [];
        }

        $connection->forceFill(['meta' => [...$connection->meta, 'social_targets' => $targets, 'social_targets_at' => CarbonImmutable::now()->toIso8601String()]])->save();

        return $targets;
    }

    /** The agent's own connection to a social tool, if any. */
    public function connection(User $user, string $integration): ?Connection
    {
        return Connection::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)->where('integration_id', $integration)->latest('id')->first();
    }

    /** @return (ConnectorDriver&PublishesPosts)|null */
    public function driver(Connection $connection): ?PublishesPosts
    {
        if ($this->registry->mode($connection->integration_id) === IntegrationMode::Off) {
            return null;
        }

        $driver = $this->registry->driver($connection->integration_id, $connection->mode);

        return $driver instanceof PublishesPosts ? $driver : null;
    }

    /**
     * @param  array<mixed>  $targets
     * @return list<Target>
     */
    private static function clean(array $targets): array
    {
        $list = [];

        foreach ($targets as $t) {
            if (is_array($t) && is_string($t['channel'] ?? null) && is_string($t['id'] ?? null) && is_string($t['name'] ?? null)) {
                $list[] = ['channel' => $t['channel'], 'id' => $t['id'], 'name' => $t['name']];
            }
        }

        return $list;
    }
}
