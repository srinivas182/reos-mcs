<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connections;

use App\Connections\ConnectorRegistry;
use App\Connections\SimulatorSignature;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationMode;
use App\Services\Connections\ConnectionAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The demo-data stand-in for a tool's own sign-in page (Simulator mode only).
 * It is plainly a REOS demo page, never a copy of the vendor's. "Allow"
 * returns a signed code bound to the PKCE challenge; "Don't allow" returns
 * `access_denied`, as a real tool would.
 */
final class SimulatorSignInController extends Controller
{
    public function show(Request $request, string $tool, ConnectorRegistry $registry, ConnectionAuthorizer $authorizer): Response
    {
        $authorization = $this->guard($request, $tool, $registry, $authorizer);

        return Inertia::render('connections/simulator', [
            'integrationId' => $tool,
            'csrfToken' => csrf_token(),
            'integration' => $registry->definition($tool)->name ?? $tool,
            'account' => $authorization['account'],
            'state' => $request->string('state')->toString(),
            'codeChallenge' => $request->string('code_challenge')->toString(),
            'redirectUri' => $request->string('redirect_uri')->toString(),
        ]);
    }

    public function decide(Request $request, string $tool, ConnectorRegistry $registry, ConnectionAuthorizer $authorizer): RedirectResponse
    {
        $authorization = $this->guard($request, $tool, $registry, $authorizer);
        $state = $request->string('state')->toString();
        $redirectUri = $request->string('redirect_uri')->toString();

        $query = $request->boolean('allow')
            ? ['code' => SimulatorSignature::seal([
                'account' => $authorization['account'],
                'challenge' => $request->string('code_challenge')->toString(),
                'redirect_uri' => $redirectUri,
            ]), 'state' => $state]
            : ['error' => 'access_denied', 'state' => $state];

        return redirect()->away($redirectUri.'?'.http_build_query($query));
    }

    /**
     * Only for tools on demo data, a genuine sign-in in progress, and REOS's own
     * callback address (never an open redirect).
     *
     * @return array{account: string}
     */
    private function guard(Request $request, string $tool, ConnectorRegistry $registry, ConnectionAuthorizer $authorizer): array
    {
        abort_unless($registry->supports($tool) && $registry->mode($tool) === IntegrationMode::Simulator, 404);
        abort_unless($request->string('redirect_uri')->toString() === ConnectionAuthorizer::redirectUri($tool), 400);
        abort_unless(preg_match('/^[A-Za-z0-9_-]{43}$/', $request->string('code_challenge')->toString()) === 1, 400);

        $authorization = $authorizer->find($request->string('state')->toString());
        abort_if($authorization === null || $authorization->used_at !== null || $authorization->expires_at->isPast(), 400);

        $user = $authorization->user;

        return ['account' => $user->email ?? $user->phone ?? 'demo@example.com'];
    }
}
