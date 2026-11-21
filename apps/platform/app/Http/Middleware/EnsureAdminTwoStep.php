<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authorization\RoleRefused;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin screens: a person using an admin role (broker, school admin,
 * association admin, super admin) without two-step is sent to set it up,
 * never just refused. The phone app gets reason `roles.errors.twoStepRequired`.
 */
final readonly class EnsureAdminTwoStep
{
    public function __construct(private ActiveRole $active) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->active->needsTwoStep($user)) {
            if ($request->expectsJson()) {
                throw RoleRefused::twoStepRequired();
            }

            return redirect()->route('security.edit')->with('status', 'two-step-required');
        }

        return $next($request);
    }
}
