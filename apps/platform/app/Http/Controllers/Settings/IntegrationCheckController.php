<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Integrations\Checks\CheckTrigger;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use App\Models\User;
use App\Services\Integrations\ConnectionChecker;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/** "Test connection": checks one environment's keys with the vendor. */
final class IntegrationCheckController extends Controller
{
    public function store(IntegrationDefinition $integration, CredentialEnvironment $environment, ConnectionChecker $checker, #[CurrentUser] User $user): RedirectResponse
    {
        $checker->check($integration, $environment, CheckTrigger::Manual, $user);

        return back();
    }
}
