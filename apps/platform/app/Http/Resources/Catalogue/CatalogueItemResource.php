<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalogue;

use App\Catalogue\CatalogueStatus;
use App\Catalogue\LibraryGroup;
use App\Connections\ConnectorRegistry;
use App\Integrations\IntegrationDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One tool in the Connections library, as this person sees it.
 *
 * @property array{definition: IntegrationDefinition, status: CatalogueStatus, group: LibraryGroup, action: string, interested: bool, retires_on: string|null, connected: int} $resource
 */
final class CatalogueItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $definition = $item['definition'];

        return [
            /** The tool's register id, for example `follow-up-boss`. */
            'id' => (string) $definition->id,
            'name' => (string) $definition->name,
            // The agent-facing line in their language; the register checks every
            // tool people can see has one.
            'purpose' => __('integrations.tools.'.$definition->id),
            /** The register category, for example `crm` or `transactions` (the website's tabs). */
            'category' => (string) $definition->category,
            /**
             * The library tab: `crm`, `deals`, `calendar`, `leads` or `more`.
             *
             * @var LibraryGroup
             */
            'group' => $item['group'],
            /**
             * `enabled`, `beta`, `coming_soon`, `paused` (temporarily unavailable) or `retired`.
             *
             * @var CatalogueStatus
             */
            'status' => $item['status'],
            /**
             * What this person can do: `connect`, `join_beta`, `notify` or `none`.
             *
             * @var 'connect'|'join_beta'|'notify'|'none'
             */
            'action' => $item['action'],
            /** They tapped "Notify me" or "Join beta". */
            'interested' => (bool) $item['interested'],
            /** Retired tools: the last day they work (YYYY-MM-DD). */
            'retires_on' => $item['retires_on'],
            /** How many accounts of theirs are connected. */
            'connected' => (int) $item['connected'],
            /** `user` (their own account) or `tenant` (their organisation's). */
            'accounts' => $definition->accounts->value,
            /**
             * For tools that can be connected: what REOS will be able to do, and what it never does (consent sheet).
             *
             * @var array{can: list<'read_events'|'change_alerts'>, never: list<'change_events'>}|null
             */
            'consent' => ConnectorRegistry::consent($definition->id),
        ];
    }
}
