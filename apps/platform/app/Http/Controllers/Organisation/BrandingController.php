<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\UpdateBrandRequest;
use App\Http\Requests\Organisation\UploadLogoRequest;
use App\Http\Resources\Tenancy\TenantDomainResource;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Tenancy\Brands;
use App\Services\Tenancy\SingleSignOn;
use App\Services\Tenancy\TenantDomains;
use App\Tenancy\Brand;
use App\Tenancy\BrandColour;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Branding and domain (association admin, web journey screen 57): the
 * brand of the organisation the person manages in their active role.
 * A4.2b adds the custom domain records and single sign-on.
 */
final class BrandingController extends Controller
{
    public function show(Request $request, #[CurrentUser] User $user, ActiveRole $activeRole, Brands $brands, TenantDomains $domains, SingleSignOn $sso): Response
    {
        $tenant = $this->tenant($user, $activeRole);
        $brand = Brand::of($tenant);
        $domain = $domains->of($tenant);

        return Inertia::render('organisation/branding', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'type' => $tenant->type->value,
                'address' => $tenant->slug.'.'.config()->string('reos.tenancy.base_domain'),
                'parent' => $tenant->parent?->name,
            ],
            'brand' => [
                ...$brand->toArray(),
                'email_from_name' => $brand->emailFromName,
                'reply_to' => $brand->replyTo,
            ],
            // What members see when this organisation has no brand of its own.
            'inherited' => $brand->isSet() ? null : $brands->forOrganisation($tenant)?->toArray(),
            'minContrast' => BrandColour::MIN_CONTRAST,
            // The tenant's own domain (A4.2b).
            'domain' => $domain === null ? null : (new TenantDomainResource($domain))->resolve($request),
            // Single sign-on (A4.2c).
            'sso' => $sso->screen($tenant),
        ]);
    }

    public function update(UpdateBrandRequest $request, #[CurrentUser] User $user, ActiveRole $activeRole, Brands $brands): RedirectResponse
    {
        $brands->update($this->tenant($user, $activeRole), $request->changes(), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('brand.saved')]);

        return back();
    }

    public function logo(UploadLogoRequest $request, #[CurrentUser] User $user, ActiveRole $activeRole, Brands $brands): RedirectResponse
    {
        $file = $request->file('logo');
        abort_unless($file instanceof UploadedFile, 422);
        $brands->setLogo($this->tenant($user, $activeRole), $file, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('brand.logoSaved')]);

        return back();
    }

    public function removeLogo(#[CurrentUser] User $user, ActiveRole $activeRole, Brands $brands): RedirectResponse
    {
        $brands->removeLogo($this->tenant($user, $activeRole), $user);

        return back();
    }

    /** The organisation of the active role; REOS itself has no tenant brand. */
    private function tenant(User $user, ActiveRole $activeRole): Organisation
    {
        $membership = $activeRole->for($user);
        abort_if($membership === null || $membership->organisation->type === OrganisationType::Platform, 404);

        return $membership->organisation;
    }
}
