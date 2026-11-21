<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Tenancy\SupportAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While REOS support is inside a tenant (A4.1c), every page it opens and
 * every change it makes goes in the tenant's audit log. Link prefetches
 * aren't views, so they're skipped.
 */
final class LogSupportViews
{
    public function __construct(private readonly SupportAccess $support) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $grant = $user instanceof User ? $this->support->current($user, $request) : null;

        if ($grant !== null && $request->header('Purpose') !== 'prefetch') {
            $this->support->logView($grant, $user, $request);
        }

        return $next($request);
    }
}
