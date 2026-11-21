<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connections;

use App\Http\Controllers\Controller;
use App\Integrations\Catalogue;
use App\Models\OAuthAuthorization;
use App\Services\Connections\ConnectionAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Where a tool sends people back after signing in (`/integrations/{id}/callback`,
 * the address Settings › Integrations shows). The single-use `state` says who
 * it is for, so it works from the phone's browser sheet too, which has no web
 * session. Website sign-ins return to the website; phone ones to the app.
 */
final class OAuthCallbackController extends Controller
{
    public function __invoke(Request $request, string $tool, ConnectionAuthorizer $authorizer, Catalogue $catalogue): RedirectResponse
    {
        $state = $request->string('state')->toString();
        $result = $authorizer->complete(
            $tool,
            $state,
            $request->filled('code') ? $request->string('code')->toString() : null,
            $request->filled('error') ? $request->string('error')->toString() : null,
        );

        // An unusable state still says where the person came from, if it's genuine.
        $authorization = $result['authorization'] ?? $authorizer->find($state);
        $summary = [
            'outcome' => $result['outcome'],
            'integration' => $tool,
            'reason' => $result['reason'],
        ];
        $name = $catalogue->find($tool)->name ?? $tool;

        if ($authorization?->return_to === OAuthAuthorization::RETURN_APP) {
            // The app opens "Connected with smart defaults" for this connection.
            $app = [...$summary, 'connection' => $result['connection']?->id];

            return redirect()->away('reos://connections?'.http_build_query(array_filter($app)));
        }

        Inertia::flash('toast', [
            'type' => $result['outcome'] === ConnectionAuthorizer::OUTCOME_CONNECTED ? 'success' : ($result['outcome'] === ConnectionAuthorizer::OUTCOME_CANCELLED ? 'info' : 'error'),
            'message' => $result['reason'] === null
                ? __('connections.outcome.connected', ['tool' => $name])
                : __('connections.outcome.'.$result['outcome'], ['tool' => $name]).' '.__($result['reason']),
        ]);

        // Also kept in the session for anything that needs the outcome itself.
        return to_route('dashboard')->with('connection', [...$summary, 'name' => $name]);
    }
}
