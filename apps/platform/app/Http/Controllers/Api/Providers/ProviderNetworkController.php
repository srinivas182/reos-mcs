<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Providers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Providers\DeclineProviderRequest;
use App\Http\Requests\Providers\ProviderPreferencesRequest;
use App\Http\Requests\Providers\ShareContactsRequest;
use App\Models\User;
use App\ProviderNetwork\ProviderConnections;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * The private network between providers and agents (B16.2, ADR 0080):
 * the agent's answer, connections with mutual contact sharing, block,
 * and the agent's choices about provider requests.
 */
final class ProviderNetworkController extends Controller
{
    /**
     * My provider requests (agent).
     *
     * Waiting requests from verified providers, my connections, and my choices (all providers, and per category).
     *
     * @response array{pending: list<array{id: string, provider: array{id: int, name: string, category: string|null, photo_url: string|null}, message: string|null, sent_at: string, expires_at: string}>, connections: list<array{id: string, side: 'agent'|'provider', with: string, provider: array{id: int, name: string, category: string|null, photo_url: string|null}, connected_at: string|null, i_shared: bool, they_shared: bool, contacts: array{email: string|null, phone: string|null}|null, conversation: string|null, blocked: bool}>, preferences: array{all: bool, categories: list<array{key: string, name: string, on: bool}>}}
     */
    public function mine(#[CurrentUser] User $user, ProviderConnections $network): JsonResponse
    {
        return response()->json($network->forAgent($user));
    }

    /**
     * Accept a provider's request.
     *
     * Opens a private chat with the provider in Messages. Contacts stay private until both press Share.
     *
     * @response array{data: array{id: string, side: 'agent'|'provider', with: string, provider: array{id: int, name: string, category: string|null, photo_url: string|null}, connected_at: string|null, i_shared: bool, they_shared: bool, contacts: array{email: string|null, phone: string|null}|null, conversation: string|null, blocked: bool}}
     */
    public function accept(string $providerRequest, #[CurrentUser] User $user, ProviderConnections $network): JsonResponse
    {
        return response()->json(['data' => $network->accept($user, $providerRequest)]);
    }

    /**
     * Decline a provider's request.
     *
     * With `block`, the provider can't ask again or message. The provider is told only that it was declined.
     *
     * @response array{declined: bool}
     */
    public function decline(DeclineProviderRequest $request, string $providerRequest, #[CurrentUser] User $user, ProviderConnections $network): JsonResponse
    {
        $network->decline($user, $providerRequest, $request->boolean('block'));

        return response()->json(['declined' => true]);
    }

    /**
     * My choices about provider requests.
     *
     * Turn requests off altogether, or for some categories.
     *
     * @response array{data: array{all: bool, categories: list<array{key: string, name: string, on: bool}>}}
     */
    public function preferences(ProviderPreferencesRequest $request, #[CurrentUser] User $user, ProviderConnections $network): JsonResponse
    {
        return response()->json(['data' => $network->setPreferences($user, $request->boolean('all'), $request->off())]);
    }

    /**
     * My connections (provider).
     *
     * Agents who accepted my requests, with contact sharing and the chat.
     *
     * @response array{data: list<array{id: string, side: 'agent'|'provider', with: string, provider: array{id: int, name: string, category: string|null, photo_url: string|null}, connected_at: string|null, i_shared: bool, they_shared: bool, contacts: array{email: string|null, phone: string|null}|null, conversation: string|null, blocked: bool}>}
     */
    public function connections(#[CurrentUser] User $user, ProviderConnections $network): JsonResponse
    {
        return response()->json(['data' => $network->forProvider($user)]);
    }

    /**
     * A connection.
     *
     * For either side. `contacts` is set only when both have shared, and never across a block.
     *
     * @response array{data: array{id: string, side: 'agent'|'provider', with: string, provider: array{id: int, name: string, category: string|null, photo_url: string|null}, connected_at: string|null, i_shared: bool, they_shared: bool, contacts: array{email: string|null, phone: string|null}|null, conversation: string|null, blocked: bool}}
     */
    public function show(string $providerRequest, #[CurrentUser] User $user, ProviderConnections $network): JsonResponse
    {
        return response()->json(['data' => $network->show($user, $providerRequest)]);
    }

    /**
     * Share my contacts on a connection.
     *
     * `share: true` shares my phone and email with the other side once they share too; `false` stops sharing.
     *
     * @response array{data: array{id: string, side: 'agent'|'provider', with: string, provider: array{id: int, name: string, category: string|null, photo_url: string|null}, connected_at: string|null, i_shared: bool, they_shared: bool, contacts: array{email: string|null, phone: string|null}|null, conversation: string|null, blocked: bool}}
     */
    public function share(ShareContactsRequest $request, string $providerRequest, #[CurrentUser] User $user, ProviderConnections $network): JsonResponse
    {
        return response()->json(['data' => $network->share($user, $providerRequest, $request->boolean('share'))]);
    }

    /**
     * Block the other side of a connection.
     *
     * No more messages or requests either way.
     *
     * @response array{blocked: bool}
     */
    public function block(string $providerRequest, #[CurrentUser] User $user, ProviderConnections $network): JsonResponse
    {
        $network->block($user, $providerRequest);

        return response()->json(['blocked' => true]);
    }
}
