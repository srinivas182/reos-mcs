<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\AddDomainRequest;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Tenancy\TenantDomains;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The tenant's own domain (A4.2b), from Branding and domain (screen 57) or
 * Brokerage settings (screen 41): add, Verify DNS, remove.
 */
final class DomainController extends Controller
{
    public function store(AddDomainRequest $request, #[CurrentUser] User $user, ActiveRole $activeRole, TenantDomains $domains): RedirectResponse
    {
        $domains->add($this->tenant($user, $activeRole), $request->hostname(), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('domains.added')]);

        return back();
    }

    public function verify(#[CurrentUser] User $user, ActiveRole $activeRole, TenantDomains $domains): RedirectResponse
    {
        $domain = $domains->of($this->tenant($user, $activeRole));
        abort_if($domain === null, 404);

        $domain = $domains->check($domain, $user);
        Inertia::flash('toast', $domain->live()
            ? ['type' => 'success', 'message' => __('domains.live', ['domain' => $domain->hostname])]
            : ['type' => 'info', 'message' => __('domains.waiting')]);

        return back();
    }

    public function destroy(#[CurrentUser] User $user, ActiveRole $activeRole, TenantDomains $domains): RedirectResponse
    {
        $domain = $domains->of($this->tenant($user, $activeRole));
        abort_if($domain === null, 404);

        $domains->remove($domain, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('domains.removed', ['domain' => $domain->hostname])]);

        return back();
    }

    private function tenant(User $user, ActiveRole $activeRole): Organisation
    {
        $membership = $activeRole->for($user);
        abort_if($membership === null || in_array($membership->organisation->type, [OrganisationType::Platform, OrganisationType::Team], true), 404);

        return $membership->organisation;
    }
}
