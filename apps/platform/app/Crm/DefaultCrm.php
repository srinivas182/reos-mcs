<?php

declare(strict_types=1);

namespace App\Crm;

use App\Connections\ConnectionStatus;
use App\Connections\ConnectorRegistry;
use App\Connections\Crm\CreatesPeople;
use App\Connections\Crm\CrmDriver;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Using two CRMs (Connections and Sync journey, screen 24, B8.3b): where a
 * contact the agent adds in REOS goes (Add contact, from Messages or a
 * showing). The same person in both CRMs is still merged into one, and the
 * newest change wins. With one CRM that takes new people, that's the
 * default; with several, the agent picks one.
 *
 * @phpstan-type Option array{connection: int, integration: string, name: string, account: string|null, takes_new_people: bool, is_default: bool}
 */
final readonly class DefaultCrm
{
    public const SETTING = 'default_for_new';

    public function __construct(private ConnectorRegistry $registry, private Sources $sources) {}

    /** @return list<Option> The agent's own connected CRMs. */
    public function options(User $agent): array
    {
        $default = $this->for($agent);
        $options = [];

        foreach ($this->crms($agent) as [$connection, $takes]) {
            $options[] = [
                'connection' => $connection->id, 'integration' => $connection->integration_id, 'name' => $this->sources->name($connection->integration_id),
                'account' => $connection->account_label, 'takes_new_people' => $takes, 'is_default' => $default?->id === $connection->id,
            ];
        }

        return $options;
    }

    /** Where new contacts go: the one the agent picked, else the only CRM that takes them, else none. */
    public function for(User $agent): ?Connection
    {
        $takers = array_values(array_filter($this->crms($agent), static fn (array $c): bool => $c[1]));

        foreach ($takers as [$connection]) {
            if (($connection->settings[self::SETTING] ?? false) === true) {
                return $connection;
            }
        }

        return count($takers) === 1 ? $takers[0][0] : null;
    }

    /** Picks the default (or none: new contacts stay in REOS). */
    public function set(User $agent, ?Connection $connection): void
    {
        DB::transaction(function () use ($agent, $connection): void {
            foreach ($this->crms($agent) as [$crm]) {
                $on = $connection !== null && $crm->id === $connection->id;
                if (($crm->settings[self::SETTING] ?? false) !== $on) {
                    $crm->forceFill(['settings' => [...$crm->settings, self::SETTING => $on]])->save();
                }
            }
        });
    }

    /** @return list<array{0: Connection, 1: bool}> Active CRM connections of the agent, and whether each takes new people. */
    private function crms(User $agent): array
    {
        $list = [];

        foreach (Connection::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $agent->id)->orderBy('id')->get() as $connection) {
            if ($connection->status !== ConnectionStatus::Active || ! $this->registry->supports($connection->integration_id)) {
                continue;
            }

            $driver = $this->registry->driver($connection->integration_id, $connection->mode);

            if ($driver instanceof CrmDriver) {
                $list[] = [$connection, $driver instanceof CreatesPeople];
            }
        }

        return $list;
    }
}
