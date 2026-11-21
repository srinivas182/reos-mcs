<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\SaveSsoRequest;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Tenancy\SingleSignOn;
use App\Services\Tenancy\SsoSignInFlow;
use App\Sso\SsoProtocol;
use App\Sso\TestIdentityProvider;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Single sign-on settings on Branding and domain (screen 57, A4.2c): save,
 * test sign-in, turn on and off.
 */
final class SsoController extends Controller
{
    public function update(SaveSsoRequest $request, #[CurrentUser] User $user, ActiveRole $activeRole, SingleSignOn $sso): RedirectResponse
    {
        $sso->save($this->tenant($user, $activeRole), $request->settings(), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('sso.saved')]);

        return back();
    }

    /** Fills in REOS's test identity provider (outside production). */
    public function useTestProvider(Request $request, #[CurrentUser] User $user, ActiveRole $activeRole, SingleSignOn $sso, TestIdentityProvider $testIdp): RedirectResponse
    {
        abort_unless($testIdp->available(), 404);
        $protocol = SsoProtocol::tryFrom($request->string('protocol')->toString()) ?? SsoProtocol::Saml;
        $sso->useTestProvider($this->tenant($user, $activeRole), $protocol, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('sso.testProviderUsed')]);

        return back();
    }

    /** Leaves REOS for the identity provider; the result shows here afterwards. */
    public function test(Request $request, #[CurrentUser] User $user, ActiveRole $activeRole, SsoSignInFlow $flow): SymfonyResponse
    {
        $tenant = $this->tenant($user, $activeRole);

        return Inertia::location($flow->test($tenant, $user, $request->getSchemeAndHttpHost().'/organisation/branding'));
    }

    public function enable(#[CurrentUser] User $user, ActiveRole $activeRole, SingleSignOn $sso): RedirectResponse
    {
        $tenant = $this->tenant($user, $activeRole);
        $sso->enable($tenant, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('sso.enabled', ['name' => $tenant->name])]);

        return back();
    }

    public function disable(#[CurrentUser] User $user, ActiveRole $activeRole, SingleSignOn $sso): RedirectResponse
    {
        $sso->disable($this->tenant($user, $activeRole), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('sso.disabled')]);

        return back();
    }

    private function tenant(User $user, ActiveRole $activeRole): Organisation
    {
        $membership = $activeRole->for($user);
        abort_if($membership === null || in_array($membership->organisation->type, [OrganisationType::Platform, OrganisationType::Team], true), 404);

        return $membership->organisation;
    }
}
