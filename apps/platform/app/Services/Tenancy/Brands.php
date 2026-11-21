<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use App\Tenancy\Brand;
use App\Tenancy\BrandColour;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tenant brands (A4.2, screens 2 and 57).
 *
 * Which brand shows:
 * - signed in: the organisation of the role in use, falling back to its
 *   parent (team → brokerage → association), so the brokerage brand wins
 *   inside brokerage areas and members of a brokerage without a brand see
 *   their association's;
 * - signed out (sign-in, sign-up): the tenant whose address this is,
 *   `<slug>.reos.com` (A4.2b adds the tenant's own domain);
 * - REOS super admins and everyone else: REOS.
 */
final readonly class Brands
{
    public const AUDIT_SUBJECT = 'brand';

    /** Logos are re-drawn at this size at most (longest side, pixels). */
    public const LOGO_MAX = 512;

    public function __construct(private AuditLogger $audit, private Tenancy $tenancy) {}

    public function current(Request $request): ?Brand
    {
        $user = $request->user();

        if ($user instanceof User) {
            $membership = app(ActiveRole::class)->for($user);

            return $membership === null || $membership->role === Role::SuperAdmin
                ? null
                : $this->forOrganisation($membership->organisation);
        }

        $tenant = $this->tenantForHost($request->getHost());

        return $tenant === null ? null : $this->forOrganisation($tenant);
    }

    /** The tenant whose address this is: its own live domain, or `<slug>.<base domain>`. */
    public function tenantForHost(string $host): ?Organisation
    {
        $base = '.'.config()->string('reos.tenancy.base_domain');
        $host = mb_strtolower($host);

        $custom = app(TenantDomains::class)->tenantFor($host);

        if ($custom !== null) {
            return Organisation::query()->find($custom);
        }

        if (! str_ends_with($host, $base)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($base));

        if ($slug === '' || str_contains($slug, '.')) {
            return null;
        }

        return Organisation::query()->where('slug', $slug)->where('type', '!=', OrganisationType::Platform->value)->first();
    }

    /** The organisation's own brand, or the nearest parent's that is set (the co-branding rule). */
    public function forOrganisation(Organisation $organisation): ?Brand
    {
        for ($current = $organisation, $depth = 0; $current !== null && $depth < 5; $current = $current->parent, $depth++) {
            if ($current->type === OrganisationType::Platform) {
                return null;
            }

            $brand = Brand::of($current);

            if ($brand->isSet()) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Save the brand settings. Colours white text can't be read on are
     * refused with the nearest shade that works.
     *
     * @param  array{primary?: string|null, app_label?: string|null, email_from_name?: string|null, reply_to?: string|null}  $changes
     */
    public function update(Organisation $tenant, array $changes, User $actor): Organisation
    {
        if (isset($changes['primary'])) {
            $colour = BrandColour::parse($changes['primary']);

            if ($colour === null) {
                throw ValidationException::withMessages(['primary' => __('brand.errors.colour')]);
            }

            if (! $colour->readable()) {
                throw ValidationException::withMessages(['primary' => __('brand.errors.contrast', [
                    'ratio' => number_format($colour->contrastWithWhite(), 1),
                    'suggestion' => $colour->suggestion()->hex,
                ])]);
            }

            $changes['primary'] = $colour->hex;
        }

        $before = $tenant->brand ?? [];
        $after = [...$before];

        foreach ($changes as $key => $value) {
            $after[$key] = $value === '' ? null : $value;
        }

        $changed = array_keys(array_filter($after, static fn (mixed $value, string $key): bool => ($before[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));

        if ($changed !== []) {
            $tenant->forceFill(['brand' => $after])->save();
            $this->audit->record('brand.updated', self::AUDIT_SUBJECT, (string) $tenant->id, [
                'changed' => $changed,
                'before' => array_intersect_key($before, array_flip($changed)),
                'after' => array_intersect_key($after, array_flip($changed)),
            ], $actor, $tenant->id);
        }

        return $tenant;
    }

    /**
     * A new logo: decoded and re-drawn as a PNG, so nothing inside the
     * uploaded file (scripts, metadata, oversized images) survives.
     */
    public function setLogo(Organisation $tenant, UploadedFile $file, User $actor): Organisation
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['logo' => __('brand.errors.logo')]);
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::LOGO_MAX / max($width, $height));
        $canvas = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, imagesx($canvas), imagesy($canvas), $width, $height);

        ob_start();
        imagepng($canvas, null, 9);
        $png = (string) ob_get_clean();

        $path = $this->tenancy->run($tenant->id, fn (): string => $this->tenancy->path('brand/logo-'.Str::lower(Str::random(12)).'.png'));
        Storage::put($path, $png);

        $old = $tenant->brand['logo_path'] ?? null;
        $tenant->forceFill(['brand' => [...($tenant->brand ?? []), 'logo_path' => $path]])->save();

        if (is_string($old) && $old !== $path) {
            Storage::delete($old);
        }

        $this->audit->record('brand.logo_changed', self::AUDIT_SUBJECT, (string) $tenant->id, ['bytes' => strlen($png)], $actor, $tenant->id);

        return $tenant;
    }

    public function removeLogo(Organisation $tenant, User $actor): Organisation
    {
        $old = $tenant->brand['logo_path'] ?? null;

        if (is_string($old)) {
            Storage::delete($old);
            $tenant->forceFill(['brand' => [...($tenant->brand ?? []), 'logo_path' => null]])->save();
            $this->audit->record('brand.logo_removed', self::AUDIT_SUBJECT, (string) $tenant->id, [], $actor, $tenant->id);
        }

        return $tenant;
    }
}
