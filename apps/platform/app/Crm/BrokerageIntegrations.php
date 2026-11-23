<?php

declare(strict_types=1);

namespace App\Crm;

use App\Catalogue\CatalogueStatus;
use App\Connections\ConnectorRegistry;
use App\Connections\Crm\MapsStageFields;
use App\Connections\Drivers\LoneWolf\LoneWolfDriver;
use App\Connections\Drivers\Salesforce\SalesforceDriver;
use App\Connections\Drivers\Salesforce\SalesforceRecords;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Integrations\AccountScope;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\ContactLink;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\IntegrationCatalogue;
use App\Services\Connections\Connections;
use App\Services\Connections\ConnectionTokens;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Brokerage integrations (broker web screen 40, B8.2): every tool REOS
 * offers people, with REOS's status, the brokerage's own account (for tools
 * a brokerage connects once), whether its agents may use it, and how many
 * have connected. Brokerages can only hide tools, never turn one on that
 * REOS hasn't released. For a brokerage CRM: counts and sync health, never
 * its agents' contacts, and the stage field mapping.
 *
 * @phpstan-type Row array{id: string, name: string, category: string, status: string, accounts: string, action: string, allowed: bool, may_hide: bool, agents_connected: int, connects_with: string, account: array{connection: int, label: string|null, status: string, last_synced_at: string|null, people: int, deals: array{count: int, due_week: int}|null, agents: int, unmatched_owners: list<string>, mapping: array{contact_stage: string|null, lead_stage: string, leads: bool}|null}|null}
 */
final readonly class BrokerageIntegrations
{
    public function __construct(
        private IntegrationCatalogue $catalogue,
        private ConnectorRegistry $registry,
        private ConnectionTokens $tokens,
        private Connections $connections,
        private AuditLogger $audit,
    ) {}

    /** @return list<Row> */
    public function rows(Organisation $brokerage): array
    {
        $hidden = $this->catalogue->hiddenFor($brokerage);
        $members = DB::table('model_has_roles')->where('organisation_id', $brokerage->id)->where('model_type', (new User)->getMorphClass())->distinct()->pluck('model_id');
        $agents = Connection::query()->where('owner_type', Connection::OWNER_USER)->whereIn('owner_id', $members)
            ->selectRaw('integration_id, count(distinct owner_id) as total')->groupBy('integration_id')->pluck('total', 'integration_id');
        $own = Connection::query()->where('owner_type', Connection::OWNER_ORGANISATION)->where('owner_id', $brokerage->id)->get()->keyBy('integration_id');
        $rows = [];

        foreach ($this->catalogue->forPeople() as $definition) {
            [$status, $action] = $this->catalogue->offerFor($definition, $brokerage->id);

            if (in_array($status, [CatalogueStatus::Retired], true)) {
                continue;
            }

            $connection = $own->get($definition->id);
            $count = $agents[$definition->id] ?? 0;
            $rows[] = [
                'id' => $definition->id, 'name' => $definition->name, 'category' => $definition->category, 'status' => $status->value,
                'accounts' => $definition->accounts->value, 'action' => $action,
                'allowed' => ! in_array($definition->id, $hidden, true), 'may_hide' => $this->catalogue->mayHide($definition->id),
                'agents_connected' => is_numeric($count) ? (int) $count : 0,
                // Lone Wolf (B9.1b): the brokerage's client ID, not a sign-in page.
                'connects_with' => ConnectorRegistry::usesApiKey($definition->id) ? 'api_key' : 'sign_in',
                'account' => $definition->accounts === AccountScope::Tenant && $connection instanceof Connection ? $this->account($connection) : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['accounts'] === 'tenant' ? 0 : 1, $a['category'], $a['name']] <=> [$b['accounts'] === 'tenant' ? 0 : 1, $b['category'], $b['name']]);

        return $rows;
    }

    /** Agents may use the tool, or not (only hiding: REOS decides what's released). */
    public function allow(Organisation $brokerage, string $integrationId, bool $allowed, User $actor): void
    {
        if (! $allowed && ! $this->catalogue->mayHide($integrationId)) {
            throw ValidationException::withMessages(['allowed' => __('brokerageIntegrations.errors.mayNotHide')]);
        }

        $this->catalogue->hide($brokerage, $integrationId, ! $allowed, $actor);
        $this->audit->record($allowed ? 'organisation.integration_allowed' : 'organisation.integration_hidden', 'integration', $integrationId, [], $actor, $brokerage->id);
    }

    /**
     * The fields a stage may be mapped to (cached ten minutes), or null when
     * the tool can't say now.
     *
     * @return array{Contact: list<array{name: string, label: string}>, Lead: list<array{name: string, label: string}>}|null
     */
    public function stageFields(Connection $connection): ?array
    {
        $mode = $this->registry->mode($connection->integration_id);

        if ($mode === IntegrationMode::Off) {
            return null;
        }

        $driver = $this->registry->driver($connection->integration_id, $mode);

        if (! $driver instanceof MapsStageFields) {
            return null;
        }

        /** @var array{Contact: list<array{name: string, label: string}>, Lead: list<array{name: string, label: string}>}|null */
        return Cache::remember('crm-stage-fields:'.$connection->id, 600, function () use ($connection, $driver): ?array {
            try {
                return $driver->stageFields($this->tokens->accessToken($connection, $this->registry->driver($connection->integration_id, $this->registry->mode($connection->integration_id))));
            } catch (AccessRevoked|VendorUnavailable|VendorRateLimited) {
                return null;
            }
        });
    }

    /**
     * Where the brokerage keeps a person's stage, checked against the fields
     * the CRM has. A new sync follows.
     *
     * @param  array{contact_stage: string|null, lead_stage: string, leads: bool}  $mapping
     */
    public function map(Connection $connection, array $mapping, User $actor): void
    {
        $fields = $this->stageFields($connection);
        $known = static fn (string $object, ?string $name): bool => $name === null || $fields === null || in_array($name, array_column($fields[$object], 'name'), true);

        if (! $known('Contact', $mapping['contact_stage']) || ! $known('Lead', $mapping['lead_stage'])) {
            throw ValidationException::withMessages(['contact_stage' => __('brokerageIntegrations.errors.unknownField')]);
        }

        $connection->forceFill(['meta' => [...$connection->meta, 'field_map' => $mapping]])->save();
        $this->audit->record('organisation.crm_mapping_updated', 'connection', (string) $connection->id, ['integration' => $connection->integration_id, ...$mapping], $actor, $connection->owner_id);
        $this->connections->syncNow($connection);
    }

    /** @return array{connection: int, label: string|null, status: string, last_synced_at: string|null, people: int, deals: array{count: int, due_week: int}|null, agents: int, unmatched_owners: list<string>, mapping: array{contact_stage: string|null, lead_stage: string, leads: bool}|null} */
    private function account(Connection $connection): array
    {
        $links = ContactLink::query()->where('connection_id', $connection->id);
        $deals = Deal::query()->where('connection_id', $connection->id);
        $dealTool = $connection->integration_id === LoneWolfDriver::INTEGRATION;
        $agents = $dealTool ? (clone $deals)->distinct()->count('user_id') : (clone $links)->distinct()->count('owner_key');

        return [
            'connection' => $connection->id, 'label' => $connection->account_label, 'status' => $connection->status->value,
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            // Counts only: the broker never sees the agents' contacts, or their deals' documents.
            'people' => $links->count(),
            'deals' => $dealTool ? [
                'count' => (clone $deals)->whereIn('stage', ['active', 'under_contract'])->count(),
                'due_week' => DealDate::query()->whereNull('done_at')->whereBetween('due_on', [now()->toDateString(), now()->addDays(7)->toDateString()])
                    ->whereIn('deal_id', (clone $deals)->whereIn('stage', ['active', 'under_contract'])->select('id'))->count(),
            ] : null,
            'agents' => $agents,
            'unmatched_owners' => array_values(array_filter((array) ($connection->meta['unmatched_owners'] ?? []), is_string(...))),
            'mapping' => $connection->integration_id === SalesforceDriver::INTEGRATION ? SalesforceRecords::mapping($connection->meta) : null,
        ];
    }
}
