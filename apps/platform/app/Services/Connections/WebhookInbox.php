<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Connections\AnswersValidation;
use App\Connections\ConnectorRegistry;
use App\Connections\SyncTrigger;
use App\Integrations\IntegrationMode;
use App\Jobs\SyncConnection;
use App\Models\Connection;
use App\Models\WebhookReceipt;
use Illuminate\Http\Request;

/**
 * `POST /webhooks/{integration}`: checks the tool's signature, refuses
 * repeats (tools retry), and queues a sync for each connection that changed.
 * Nothing slow happens in the request, so the tool gets its answer at once.
 */
final readonly class WebhookInbox
{
    public const ACCEPTED = 'accepted';

    public const DUPLICATE = 'duplicate';

    public const REFUSED = 'refused';

    public function __construct(private ConnectorRegistry $registry) {}

    /**
     * The tool checking REOS's address before it subscribes (Microsoft
     * Graph): the plain-text answer, or null when this isn't a check.
     */
    public function validation(string $integrationId, Request $request): ?string
    {
        $mode = $this->registry->mode($integrationId);

        if (! $this->registry->supports($integrationId) || $mode === IntegrationMode::Off) {
            return null;
        }

        $driver = $this->registry->driver($integrationId, $mode);

        return $driver instanceof AnswersValidation ? $driver->validationAnswer($request) : null;
    }

    public function receive(string $integrationId, Request $request): string
    {
        $mode = $this->registry->mode($integrationId);

        if (! $this->registry->supports($integrationId) || $mode === IntegrationMode::Off) {
            return self::REFUSED;
        }

        $delivery = $this->registry->driver($integrationId, $mode)->verifyWebhook($request);

        if ($delivery === null) {
            return self::REFUSED;
        }

        $key = mb_substr($delivery->deliveryKey, 0, 255);

        // Insert-or-ignore rather than catching the unique error: on PostgreSQL a
        // failed insert would abort any surrounding transaction.
        $inserted = WebhookReceipt::query()->insertOrIgnore([
            'integration_id' => $integrationId,
            'delivery_key' => $key,
            'received_at' => now(),
        ]);

        if ($inserted === 0) {
            return self::DUPLICATE;
        }

        if ($delivery->changed) {
            $connections = Connection::query()
                ->whereIn('id', $delivery->connectionIds)
                ->where('integration_id', $integrationId)
                ->where('mode', $mode)
                ->get();

            foreach ($connections as $connection) {
                if ($delivery->channelsLost) {
                    // Opened again after the sync these notifications start.
                    $connection->forceFill(['meta' => [...$connection->meta, 'channels' => []]])->save();
                }

                foreach (array_keys($this->registry->driver($integrationId, $mode)->streams()) as $stream) {
                    SyncConnection::dispatch($connection->id, $stream, SyncTrigger::Webhook);
                }
            }
        }

        WebhookReceipt::query()->where('integration_id', $integrationId)->where('delivery_key', $key)->update(['processed_at' => now()]);

        return self::ACCEPTED;
    }
}
