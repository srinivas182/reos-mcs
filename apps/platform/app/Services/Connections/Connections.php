<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Authorization\Permission;
use App\Connections\ConnectorRegistry;
use App\Integrations\AccountScope;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Whose connections a person sees and manages: their own, and their active
 * organisation's when their role there can manage it.
 */
final readonly class Connections
{
    public function __construct(
        private ConnectorRegistry $registry,
        private ActiveRole $activeRole,
        private AuditLogger $audit,
    ) {}

    /** @return Collection<int, Connection> */
    public function visibleTo(User $user): Collection
    {
        $organisation = $this->managedOrganisation($user);

        return Connection::query()
            ->where(static function ($query) use ($user, $organisation): void {
                $query->where(static fn ($q) => $q->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id));

                if ($organisation !== null) {
                    $query->orWhere(static fn ($q) => $q->where('owner_type', Connection::OWNER_ORGANISATION)->where('owner_id', $organisation));
                }
            })
            ->orderBy('integration_id')
            ->orderBy('id')
            ->get();
    }

    public function canManage(User $user, Connection $connection): bool
    {
        return match ($connection->owner_type) {
            Connection::OWNER_USER => $connection->owner_id === $user->id,
            Connection::OWNER_ORGANISATION => $this->managedOrganisation($user) === $connection->owner_id,
            default => false,
        };
    }

    /**
     * Who a new connection to this tool belongs to: the person for personal
     * tools, their active organisation for organisation tools (if they may).
     *
     * @return array{0: string, 1: int}|null
     */
    public function ownerFor(User $user, string $integrationId): ?array
    {
        $definition = $this->registry->definition($integrationId);

        if ($definition === null) {
            return null;
        }

        if ($definition->accounts === AccountScope::User) {
            return [Connection::OWNER_USER, $user->id];
        }

        $organisation = $this->managedOrganisation($user);

        return $organisation === null ? null : [Connection::OWNER_ORGANISATION, $organisation];
    }

    /** Tools people can connect now: in the engine and not switched off. */
    public function available(string $integrationId): bool
    {
        return $this->registry->supports($integrationId) && $this->registry->mode($integrationId) !== IntegrationMode::Off;
    }

    /** Tell the tool to forget REOS, delete the synced data and the tokens. */
    public function disconnect(Connection $connection, User $actor): void
    {
        $mode = $this->registry->mode($connection->integration_id);

        if ($this->registry->supports($connection->integration_id) && $mode === $connection->mode && $mode !== IntegrationMode::Off) {
            $driver = $this->registry->driver($connection->integration_id, $mode);

            try {
                $driver->revoke($connection->refresh_token ?? $connection->access_token ?? '');
            } catch (Throwable) {
                // Best effort; REOS forgets the tokens either way.
            }

            foreach ($driver->streams() as $store) {
                app($store)->forget($connection);
            }
        }

        $this->audit->record('connection.disconnected', 'connection', (string) $connection->id, [
            'integration' => $connection->integration_id,
            'owner' => $connection->ownerKey(),
        ], $actor);

        // Synced rows cascade with the connection.
        $connection->delete();
    }

    private function managedOrganisation(User $user): ?int
    {
        $active = $this->activeRole->for($user);

        return $active !== null && $user->can(Permission::OrganisationManage->value) ? $active->organisation->id : null;
    }
}
