<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Identity\WebSignIn;
use App\Services\Tenancy\SingleSignOn;
use App\Services\Tenancy\SsoSignInFlow;
use App\Sso\SamlClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Sign in with Texas REALTORS® (single sign-on)" (journey screen 2, A4.2c).
 * The callback is a GET for both protocols (SAML's POST arrives through
 * {@see CrossSitePostHandoff}).
 */
final class SsoSignInController extends Controller
{
    public const METHOD = 'sso';

    public function start(Organisation $organisation, Request $request, SsoSignInFlow $flow): RedirectResponse
    {
        return redirect()->away($flow->start($organisation, $request));
    }

    public function callback(Organisation $organisation, Request $request, SsoSignInFlow $flow, WebSignIn $signIn): RedirectResponse
    {
        // A SAML provider posts from its own site; routes/handoff.php parked
        // the answer and sent the browser here with a same-site GET.
        CrossSitePostHandoff::restore($request);

        $result = $flow->finish($organisation, $request);

        if ($result['next'] !== null) {
            return redirect()->away($result['next']);
        }

        return $signIn->complete($request, $result['user'] ?? abort(500), self::METHOD);
    }

    /** Back on the address the person started on (a brokerage's domain). */
    public function complete(string $ticket, Request $request, SsoSignInFlow $flow, WebSignIn $signIn): RedirectResponse
    {
        return $signIn->complete($request, $flow->redeem($ticket, $request), self::METHOD);
    }

    /** REOS's SAML metadata, for the identity provider's admin. */
    public function metadata(Organisation $organisation, SingleSignOn $sso, SamlClient $saml): Response
    {
        return response($saml->serviceProviderMetadata($sso->serviceProvider($organisation)), 200, [
            'Content-Type' => 'application/samlmetadata+xml',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
