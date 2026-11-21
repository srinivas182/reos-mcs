<?php

declare(strict_types=1);

namespace App\Http\Resources\Connections;

use App\Connections\ConnectionStatus;
use App\Integrations\Catalogue;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One connection as people see it: the tool, the account, its health and
 * when it last synced. Never the tokens.
 *
 * @property Connection $resource
 */
final class ConnectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $connection = $this->resource;

        return [
            'id' => (int) $connection->id,
            /** The tool's register id, for example `google-workspace`. */
            'integration' => (string) $connection->integration_id,
            'integration_name' => (string) (app(Catalogue::class)->find($connection->integration_id)->name ?? $connection->integration_id),
            /** `user` (yours) or `organisation` (your organisation's). */
            'owner' => (string) $connection->owner_type,
            /** The account at the tool, usually its email. */
            'account' => $connection->account_label,
            /**
             * `active`, `reconnect_needed` or `failing`.
             *
             * @var ConnectionStatus
             */
            'status' => $connection->status,
            /** Running on demo data (the Simulator), not the real tool. */
            'demo' => $connection->mode->value === 'simulator',
            /** What REOS may do at the tool. */
            'scopes' => $connection->scopes,
            /** How far back the first sync reached: `3m`, `12m` or `all`. */
            'history' => $connection->history()->value,
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            /** Translation key for the latest problem, or null. */
            'problem' => $connection->last_error,
            'connected_at' => $connection->created_at?->toIso8601String(),
        ];
    }
}
