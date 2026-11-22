<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Features\Flags;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A feature behind a flag (A6.2): people outside its rollout get a 404, as
 * if it weren't there. Use as `RequireFlag::class.':messages'`.
 */
final readonly class RequireFlag
{
    public function __construct(private Flags $flags) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        $user = $request->user() ?? $request->user('sanctum');

        abort_unless($user instanceof User && $this->flags->forUser($user, $flag), 404);

        return $next($request);
    }
}
