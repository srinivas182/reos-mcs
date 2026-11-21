<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogueSettingsRequest;
use App\Integrations\IntegrationDefinition;
use App\Models\User;
use App\Services\Catalogue\IntegrationCatalogue;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** A tool's catalogue settings (web journey screen 65): availability and the kill switch. */
final class CatalogueController extends Controller
{
    public function update(CatalogueSettingsRequest $request, IntegrationDefinition $integration, IntegrationCatalogue $catalogue, #[CurrentUser] User $user): RedirectResponse
    {
        $catalogue->update($integration, $request->changes(), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalogue.saved', ['name' => $integration->name])]);

        return back();
    }

    /** Pause (kill switch) or resume. People see "Temporarily unavailable"; data is kept; syncing stops. */
    public function pause(Request $request, IntegrationDefinition $integration, IntegrationCatalogue $catalogue, #[CurrentUser] User $user): RedirectResponse
    {
        $paused = $request->boolean('paused');
        $catalogue->pause($integration, $paused, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __($paused ? 'catalogue.pausedToast' : 'catalogue.resumedToast', ['name' => $integration->name])]);

        return back();
    }
}
