<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\IntegrationCredentialsRequest;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use App\Models\User;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/** Saves or removes one integration's test or live keys (encrypted, audited). */
final class IntegrationCredentialController extends Controller
{
    public function update(IntegrationCredentialsRequest $request, IntegrationHub $hub, #[CurrentUser] User $user): RedirectResponse
    {
        $hub->saveCredentials($request->definition(), $request->environment(), $request->values(), $user);

        return back();
    }

    public function destroy(IntegrationDefinition $integration, CredentialEnvironment $environment, IntegrationHub $hub, #[CurrentUser] User $user): RedirectResponse
    {
        $hub->removeCredentials($integration, $environment, $user);

        return back();
    }
}
