<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tenants\SupportAccessRequest;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Tenancy\SupportAccess;
use App\Tenancy\SupportAccessStatus;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** REOS support's side of support sign-in (A4.1c, screen 60): ask, then start once approved. */
final class TenantSupportController extends Controller
{
    public function store(SupportAccessRequest $request, Organisation $tenant, SupportAccess $support, #[CurrentUser] User $actor): RedirectResponse
    {
        $support->ask($tenant, $actor, $request->reason());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('support.asked', ['tenant' => $tenant->name])]);

        return back();
    }

    public function start(Request $request, Organisation $tenant, SupportAccess $support, #[CurrentUser] User $actor): RedirectResponse
    {
        $grant = $support->latest($tenant);

        if ($grant === null || $grant->status !== SupportAccessStatus::Approved) {
            throw ValidationException::withMessages(['grant' => __('support.errors.notApproved')]);
        }

        $support->start($grant, $actor, $request);

        return to_route('dashboard');
    }
}
