<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Connections\ConnectorDriver;
use App\Connections\ConnectorRegistry;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\CursorExpired;
use App\Connections\StreamStore;
use App\Connections\SubscribesToChanges;
use App\Connections\SyncTrigger;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\SyncRun;
use Throwable;

/**
 * Runs one stream of one connection for a few pages at a time (ADR 0013).
 *
 * - The first sync starts at the chosen history; later ones carry on from
 *   the saved cursor, which is saved after every page, so a crash or retry
 *   resumes where it stopped. Upserts are idempotent.
 * - After `PAGES_PER_RUN` pages the job hands back the queue (`continued`)
 *   and re-queues itself, so one big calendar can't starve other people.
 * - A refused token gets one refresh; refused again, the connection needs
 *   reconnecting. Other errors go back to the job for retry with backoff.
 * - A tool switched off (or paused) is skipped; one switched to another run
 *   mode needs reconnecting, because its tokens belong to the old mode.
 */
final readonly class SyncEngine
{
    public const PAGES_PER_RUN = 5;

    public const SKIPPED = 'skipped';

    public function __construct(
        private ConnectorRegistry $registry,
        private ConnectionTokens $tokens,
        private ConnectionHealth $health,
    ) {}

    /** @return string `synced`, `continued`, `skipped` or `failed` (needs reconnecting). */
    public function run(Connection $connection, string $stream, SyncTrigger $trigger): string
    {
        $mode = $this->registry->mode($connection->integration_id);

        if ($mode === IntegrationMode::Off || ! $connection->status->syncs()) {
            return self::SKIPPED;
        }

        if ($mode !== $connection->mode) {
            $this->health->reconnectNeeded($connection, 'connections.errors.modeChanged');

            return SyncRun::OUTCOME_FAILED;
        }

        $driver = $this->registry->driver($connection->integration_id, $mode);
        $storeClass = $driver->streams()[$stream] ?? null;

        if ($storeClass === null) {
            return self::SKIPPED;
        }

        /** @var StreamStore $store */
        $store = app($storeClass);
        $run = SyncRun::query()->create([
            'connection_id' => $connection->id,
            'stream' => $stream,
            'trigger' => $trigger,
            'started_at' => now(),
        ]);

        try {
            $outcome = $this->pages($connection, $driver, $store, $stream, $run);
        } catch (AccessRevoked) {
            $this->finish($run, SyncRun::OUTCOME_FAILED, 'connections.errors.refused');
            $this->health->reconnectNeeded($connection, 'connections.errors.refused');

            return SyncRun::OUTCOME_FAILED;
        } catch (Throwable $e) {
            // Retried by the job; health counts it if the retries run out.
            $this->finish($run, SyncRun::OUTCOME_FAILED, 'connections.errors.vendorUnavailable');

            throw $e;
        }

        $this->finish($run, $outcome);

        if ($outcome === SyncRun::OUTCOME_SYNCED) {
            $this->health->synced($connection);
            $this->subscribe($connection, $driver);
        }

        return $outcome;
    }

    private function pages(Connection $connection, ConnectorDriver $driver, StreamStore $store, string $stream, SyncRun $run): string
    {
        $refreshed = false;
        $restarted = false;

        for ($page = 0; $page < self::PAGES_PER_RUN; $page++) {
            $cursor = $connection->cursor($stream);

            try {
                $result = $driver->fetch(
                    $connection,
                    $this->tokens->accessToken($connection, $driver),
                    $stream,
                    $cursor,
                    $cursor === null ? $connection->history()->since() : null,
                );
            } catch (AccessRevoked $e) {
                if ($refreshed) {
                    throw $e;
                }

                // An access token can be refused early; try one refresh before giving up.
                $refreshed = true;
                $this->tokens->accessToken($connection, $driver, forceRefresh: true);
                $page--;

                continue;
            } catch (CursorExpired $e) {
                if ($restarted) {
                    throw $e;
                }

                $restarted = true;
                $this->saveCursor($connection, $stream, null);
                $page--;

                continue;
            }

            $run->upserted += $store->upsert($connection, $result->records);
            $run->removed += $store->remove($connection, $result->removed);
            $run->pages++;
            $this->saveCursor($connection, $stream, $result->cursor);

            if ($result->done) {
                return SyncRun::OUTCOME_SYNCED;
            }
        }

        return SyncRun::OUTCOME_CONTINUED;
    }

    private function saveCursor(Connection $connection, string $stream, ?string $cursor): void
    {
        $cursors = $connection->cursors;

        if ($cursor === null) {
            unset($cursors[$stream]);
        } else {
            $cursors[$stream] = $cursor;
        }

        $connection->forceFill(['cursors' => $cursors])->save();
    }

    private function finish(SyncRun $run, string $outcome, ?string $error = null): void
    {
        $run->forceFill(['outcome' => $outcome, 'error' => $error, 'finished_at' => now()])->save();
    }

    /**
     * Ask the tool to push changes, once, when REOS has a public HTTPS address
     * (tools won't call localhost). Without it the 15-minute schedule catches up.
     */
    private function subscribe(Connection $connection, ConnectorDriver $driver): void
    {
        $webhookUrl = url("webhooks/{$connection->integration_id}");

        if (! $driver instanceof SubscribesToChanges || isset($connection->meta['channel_id']) || ! str_starts_with($webhookUrl, 'https://')) {
            return;
        }

        try {
            $meta = $driver->subscribe($connection, $this->tokens->accessToken($connection, $driver), $webhookUrl);
            $connection->forceFill(['meta' => [...$connection->meta, ...$meta]])->save();
        } catch (Throwable) {
            // Push is a bonus; scheduled syncs keep the data fresh.
        }
    }
}
