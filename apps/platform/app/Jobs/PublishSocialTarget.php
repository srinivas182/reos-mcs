<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Connections\ConnectionStatus;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Models\SocialPostTarget;
use App\Services\Connections\ConnectionTokens;
use App\Social\SocialAccounts;
use App\Social\SocialPosts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Publishes one post to one place (B11, ADR 0046), retried with backoff.
 * A connection that needs reconnecting fails at once and says so; the
 * post's status follows its places.
 */
final class PublishSocialTarget implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $target)
    {
        $this->onQueue('connections');
    }

    public function handle(SocialAccounts $accounts, ConnectionTokens $tokens): void
    {
        $target = SocialPostTarget::query()->with('post', 'connection')->find($this->target);

        if ($target === null || $target->status === 'published' || $target->post->trashed()) {
            return;
        }

        $connection = $target->connection;
        $driver = $connection === null ? null : $accounts->driver($connection);

        if ($connection === null || $driver === null || $connection->status === ConnectionStatus::ReconnectNeeded) {
            $this->give($target, 'reconnect');

            return;
        }

        $target->forceFill(['status' => 'publishing', 'attempts' => $target->attempts + 1])->save();

        try {
            $done = $driver->publish($connection, $tokens->accessToken($connection, $driver), ['channel' => $target->channel, 'id' => $target->target_id, 'name' => $target->target_name], SocialPosts::content($target->post));
        } catch (VendorRateLimited $e) {
            $target->forceFill(['status' => 'queued'])->save();
            $this->release($e->retryAfterSeconds);

            return;
        } catch (AccessRevoked) {
            $connection->forceFill(['status' => ConnectionStatus::ReconnectNeeded])->save();
            $this->give($target, 'reconnect');

            return;
        } catch (Throwable $e) {
            $target->forceFill(['status' => 'queued', 'last_error' => mb_substr($e->getMessage(), 0, 300)])->save();

            throw $e;
        }

        $target->forceFill(['status' => 'published', 'external_id' => $done['id'], 'url' => $done['url'], 'published_at' => now(), 'last_error' => null])->save();
        SocialPosts::rollUp($target->post);
    }

    /** Out of tries: the place failed, with the last error. */
    public function failed(?Throwable $e): void
    {
        $target = SocialPostTarget::query()->with('post')->find($this->target);

        if ($target !== null && $target->status !== 'published') {
            $this->give($target, $e === null ? 'failed' : mb_substr($e->getMessage(), 0, 300));
        }
    }

    private function give(SocialPostTarget $target, string $error): void
    {
        $target->forceFill(['status' => 'failed', 'last_error' => $error])->save();
        SocialPosts::rollUp($target->post);
    }
}
