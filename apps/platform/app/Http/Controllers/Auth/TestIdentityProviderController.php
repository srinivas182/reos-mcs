<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Sso\TestIdentityProvider;
use Database\Seeders\Demo\Persona;
use Database\Seeders\Demo\Personas;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sign-in page of REOS's test identity provider (outside production):
 * pick who signs in. Each choice is a ready, signed answer, sent to REOS
 * the way a real provider would (redirect for OIDC, form post for SAML).
 */
final class TestIdentityProviderController extends Controller
{
    /** Someone with no REOS account yet, to show a first sign-in creating one. */
    private const NEW_MEMBER = ['name' => 'Maria Lopez', 'email' => 'maria.lopez@members.texasrealtors.example', 'title' => 'New member', 'organisation' => 'Texas REALTORS®'];

    public function oidc(Request $request, TestIdentityProvider $idp): Response
    {
        abort_unless($idp->available() && $request->query('client_id') === TestIdentityProvider::CLIENT_ID, 404);
        $redirect = $request->string('redirect_uri')->toString();
        abort_unless(preg_match('#^https?://[^/]+/sso/[a-z0-9-]+/callback$#', $redirect) === 1, 400);

        return $this->page('oidc', fn (array $person): array => [
            'action' => $redirect,
            'method' => 'get',
            'fields' => ['code' => $idp->issueCode($request->query(), $person['email'], $person['name']), 'state' => $request->string('state')->toString()],
        ]);
    }

    public function saml(Request $request, TestIdentityProvider $idp): Response
    {
        abort_unless($idp->available(), 404);
        $saml = $idp->readRequest($request->string('SAMLRequest')->toString());
        abort_unless($saml !== null && preg_match('#^https?://[^/]+/sso/[a-z0-9-]+/callback$#', $saml['acs']) === 1, 400);

        return $this->page('saml', fn (array $person): array => [
            'action' => $saml['acs'],
            'method' => 'post',
            'fields' => [
                'SAMLResponse' => $idp->samlResponse($saml['acs'], $saml['issuer'], $saml['id'], $person['email'], $person['name']),
                'RelayState' => $request->string('RelayState')->toString(),
            ],
        ]);
    }

    /** @param  \Closure(array{name: string, email: string, title: string, organisation: string}): array<string, mixed>  $answer */
    private function page(string $protocol, \Closure $answer): Response
    {
        $people = [
            ...array_map(static fn (Persona $persona): array => ['name' => $persona->name, 'email' => $persona->email, 'title' => $persona->title, 'organisation' => $persona->organisation], Personas::all()),
            self::NEW_MEMBER,
        ];

        return Inertia::render('auth/test-identity-provider', [
            'protocol' => $protocol,
            'people' => array_map(static fn (array $person): array => [...$person, ...$answer($person)], $people),
        ]);
    }
}
