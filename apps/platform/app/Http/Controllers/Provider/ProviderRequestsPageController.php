<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\ProviderNetwork\ProviderConnections;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Provider requests on the web (B16.2, agent journey): waiting requests, connections and my choices. */
final class ProviderRequestsPageController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, ProviderConnections $network): Response
    {
        return Inertia::render('providers/requests', ['network' => $network->forAgent($user)]);
    }
}
