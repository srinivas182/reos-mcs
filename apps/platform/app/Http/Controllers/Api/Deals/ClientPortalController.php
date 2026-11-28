<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Deals;

use App\Deals\ClientPortals;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CreatePortalRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Sharing a deal with a client through their own link (B14.2, ADR 0065). */
final class ClientPortalController extends Controller
{
    /**
     * The deal's client links.
     *
     * @response array{data: list<array{id: string, name: string, email: string|null, url: string, opened_at: string|null, sent_at: string|null, expires_at: string, review: array{rating: int, publish: bool}|null}>}
     */
    public function index(string $deal, #[CurrentUser] User $user, ClientPortals $portals): JsonResponse
    {
        return response()->json(['data' => $portals->list($user, $deal)]);
    }

    /**
     * Share the deal with a client.
     *
     * A private link for the client: the deal's stage and key dates, documents to sign, and a review request after closing. No account needed. The same client's open link is reused. Signers with a link sign in it.
     *
     * @response array{data: array{id: string, name: string, email: string|null, url: string, opened_at: string|null, sent_at: string|null, expires_at: string, review: array{rating: int, publish: bool}|null}}
     */
    public function store(CreatePortalRequest $request, string $deal, #[CurrentUser] User $user, ClientPortals $portals): JsonResponse
    {
        $email = $request->filled('email') ? $request->string('email')->toString() : null;

        return response()->json(['data' => $portals->create($user, $deal, $request->string('name')->toString(), $email, $request->string('language', 'en')->toString())]);
    }

    /**
     * Email the link to the client.
     *
     * @response array{data: array{id: string, name: string, email: string|null, url: string, opened_at: string|null, sent_at: string|null, expires_at: string, review: array{rating: int, publish: bool}|null}}
     */
    public function send(string $portal, #[CurrentUser] User $user, ClientPortals $portals): JsonResponse
    {
        return response()->json(['data' => $portals->send($user, $portal)]);
    }

    /** Turn a client's link off. */
    public function destroy(string $portal, #[CurrentUser] User $user, ClientPortals $portals): JsonResponse
    {
        $portals->revoke($user, $portal);

        return response()->json(null, 204);
    }
}
