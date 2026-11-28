<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Providers\AgentsFilterRequest;
use App\Models\User;
use App\ProviderNetwork\AgentsInArea;
use App\ProviderNetwork\ProviderRequests;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Agents in my areas on the web (B16.1, provider journey): agents by level, filters, requests and the month's limit. */
final class AgentsPageController extends Controller
{
    public function __invoke(AgentsFilterRequest $request, #[CurrentUser] User $user, AgentsInArea $area, ProviderRequests $requests): Response|RedirectResponse
    {
        try {
            return Inertia::render('provider/agents', ['view' => $area->for($user, $request->level(), $request->area()), 'sent' => $requests->sent($user)]);
        } catch (ValidationException) {
            // Not live yet: joining comes first.
            return redirect()->route('provider-join.show');
        }
    }
}
