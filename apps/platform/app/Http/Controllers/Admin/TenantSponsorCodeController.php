<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Billing\SponsorCodes;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\SponsorCode;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sponsor codes on a tenant's Plan tab (B5.1b; screen 60): REOS issues a
 * code for seats the tenant pays for, and stops it. B5.3 lets sponsors fund
 * them from their own pools.
 */
final class TenantSponsorCodeController extends Controller
{
    public function store(Request $request, Organisation $tenant, #[CurrentUser] User $actor, SponsorCodes $codes): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', 'live')],
            'state' => ['required', 'string', 'size:2', Rule::exists('states', 'code')],
            'seats' => ['required', 'integer', 'min:1', 'max:10000'],
            'access_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ]);

        $code = $codes->create(
            $tenant,
            Product::query()->whereKey(is_numeric($data['product_id']) ? (int) $data['product_id'] : 0)->firstOrFail(),
            strtoupper(is_string($data['state']) ? $data['state'] : ''),
            is_numeric($data['seats']) ? (int) $data['seats'] : 1,
            isset($data['access_months']) && is_numeric($data['access_months']) ? (int) $data['access_months'] : null,
            isset($data['expires_on']) && is_string($data['expires_on']) ? CarbonImmutable::parse($data['expires_on'])->endOfDay() : null,
            $actor,
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('billing.codes.created', ['code' => $code->code])]);
    }

    public function destroy(Organisation $tenant, SponsorCode $code, #[CurrentUser] User $actor, SponsorCodes $codes): RedirectResponse
    {
        abort_unless($code->sponsor_organisation_id === $tenant->id, 404);
        $codes->deactivate($code, $actor);

        return back();
    }
}
