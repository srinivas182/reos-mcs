<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For system endpoints that are authenticated by a signature or a one-time
 * `state` rather than by a person: vendor webhooks and sign-in callbacks.
 * They act for whichever tenant the signed data names (ADR 0015).
 *
 * Runs after ResolveTenant (both route groups have it), whose `terminate()`
 * puts the previous state back.
 */
final class PlatformScope
{
    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->tenancy->enterPlatform();

        return $next($request);
    }
}
