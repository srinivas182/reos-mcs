<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connections;

use App\Http\Controllers\Controller;
use App\Models\OAuthAuthorization;
use App\Services\Connections\ConnectionAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where a tool sends people back after signing in (`/integrations/{id}/callback`,
 * the address Settings › Integrations shows). The single-use `state` says who
 * it is for, so it works from the phone's browser sheet too, which has no web
 * session. Website sign-ins return to the website; phone ones to the app.
 */
final class OAuthCallbackController extends Controller
{
    public function __invoke(Request $request, string $tool, ConnectionAuthorizer $authorizer): RedirectResponse
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

        if ($authorization?->return_to === OAuthAuthorization::RETURN_APP) {
            return redirect()->away('reos://connections?'.http_build_query(array_filter($summary)));
        }

        return to_route('dashboard')->with('connection', $summary);
    }
}
