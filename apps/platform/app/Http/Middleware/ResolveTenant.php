<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets whose data this request may touch (ADR 0015), before route model
 * binding runs: the organisation of the role in use; platform for REOS
 * super admins; none for visitors and people without an organisation.
 * A4.2 adds the tenant's own domain as the first source.
 */
final class ResolveTenant
{
    public function __construct(private readonly Tenancy $tenancy, private readonly ActiveRole $activeRole) {}

    /** @var array{0: int|null, 1: bool}|null What was there before this request (tests, Octane). */
    private ?array $before = null;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->before = $this->tenancy->snapshot();
        $user = $request->user() ?? $request->user('sanctum');
        $membership = $user instanceof User ? $this->activeRole->for($user) : null;

        match (true) {
            $membership === null => $this->tenancy->enterNone(),
            $membership->role === Role::SuperAdmin, $membership->organisation->type === OrganisationType::Platform => $this->tenancy->enterPlatform(),
            default => $this->tenancy->enter($membership->organisation->id),
        };

        return $next($request);
    }

    public function terminate(): void
    {
        if ($this->before !== null) {
            $this->tenancy->restore($this->before);
        }
    }
}
