<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Monitoring\MonitoringRuntime;
use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the current monitoring settings, and records one trace span per
 * request when Grafana is on: method, route pattern, status and duration.
 * No query strings, user ids or request bodies (personal data stays home).
 */
final class TraceRequests
{
    private ?SpanInterface $span = null;

    public function __construct(private readonly MonitoringRuntime $runtime) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->runtime->apply();
        $tracer = $this->runtime->tracer();

        if ($tracer === null) {
            return $next($request);
        }

        $method = $request->method() ?: 'HTTP';

        $this->span = $tracer->spanBuilder($method)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.request.method', $method)
            ->setAttribute('url.scheme', $request->getScheme())
            ->startSpan();

        $response = $next($request);

        $route = $request->route()?->uri();
        $status = $response->getStatusCode();

        $this->span->updateName($route === null ? $method : "{$method} /{$route}");
        $this->span->setAttribute('http.route', $route === null ? null : "/{$route}");
        $this->span->setAttribute('http.response.status_code', $status);

        if ($status >= 500) {
            $this->span->setStatus(StatusCode::STATUS_ERROR);
        }

        $this->span->end();
        $this->span = null;

        return $response;
    }

    /** After the response is sent: on PHP-FPM the process ends, so send now. */
    public function terminate(Request $request, Response $response): void
    {
        if (! isset($_SERVER['LARAVEL_OCTANE'])) {
            $this->runtime->flush();
        }
    }
}
