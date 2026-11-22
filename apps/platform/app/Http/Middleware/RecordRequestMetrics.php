<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Monitoring\RequestMetrics;
use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts each request and how long it took (A6.1; screen 58), and notes
 * when a signed-in person was last active (at most once a minute), for
 * "people online now". No URLs, ids or bodies are kept.
 */
final readonly class RecordRequestMetrics
{
    public function __construct(private RequestMetrics $metrics, private Cache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $started = $request->server('REQUEST_TIME_FLOAT');
            $milliseconds = is_float($started) ? (microtime(true) - $started) * 1000 : 0.0;
            $this->metrics->record($milliseconds, $response->getStatusCode());

            $user = $request->user();

            if ($user instanceof User && $this->cache->add('active:'.$user->id, true, now()->addMinute())) {
                User::query()->whereKey($user->id)->toBase()->update(['last_active_at' => now()]);
            }
        } catch (Throwable $e) {
            // Metrics never break a request.
            report($e);
        }
    }
}
