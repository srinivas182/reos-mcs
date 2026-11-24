<?php

declare(strict_types=1);

namespace App\Social;

use App\Connections\ConnectionStatus;
use App\Connections\Exceptions\AccessRevoked;
use App\Models\SocialPostTarget;
use App\Services\Connections\ConnectionTokens;
use Throwable;

/**
 * Insights read back from each channel (B11, ADR 0046): hourly, for posts
 * published in the last 30 days (a post's numbers settle after that).
 */
final readonly class SocialInsights
{
    public const DAYS = 30;

    public function __construct(private SocialAccounts $accounts, private ConnectionTokens $tokens) {}

    public function refresh(): int
    {
        $count = 0;

        SocialPostTarget::query()->with('connection')->where('status', 'published')->whereNotNull('external_id')
            ->where('published_at', '>=', now()->subDays(self::DAYS))
            ->where(static fn ($q) => $q->whereNull('insights_at')->orWhere('insights_at', '<=', now()->subMinutes(55)))
            ->orderBy('insights_at')->limit(500)->get()
            ->each(function (SocialPostTarget $target) use (&$count): void {
                if ($this->read($target)) {
                    $count++;
                }
            });

        return $count;
    }

    public function read(SocialPostTarget $target): bool
    {
        $connection = $target->connection;
        $driver = $connection === null ? null : $this->accounts->driver($connection);

        if ($connection === null || $driver === null || $target->external_id === null || $connection->status === ConnectionStatus::ReconnectNeeded) {
            return false;
        }

        try {
            $insights = $driver->insights($connection, $this->tokens->accessToken($connection, $driver), ['channel' => $target->channel, 'id' => $target->target_id, 'name' => $target->target_name], $target->external_id);
        } catch (AccessRevoked) {
            $connection->forceFill(['status' => ConnectionStatus::ReconnectNeeded])->save();

            return false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $target->forceFill(['insights' => [...($target->insights ?? []), ...$insights], 'insights_at' => now()])->save();

        return true;
    }
}
