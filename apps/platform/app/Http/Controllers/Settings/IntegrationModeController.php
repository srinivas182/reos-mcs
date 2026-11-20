<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\IntegrationModeRequest;
use App\Models\User;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/** Switches an integration between off, demo data, the vendor's test system and live. */
final class IntegrationModeController extends Controller
{
    public function update(IntegrationModeRequest $request, IntegrationHub $hub, #[CurrentUser] User $user): RedirectResponse
    {
        $hub->setMode($request->definition(), $request->mode(), $user);

        return back();
    }
}
