<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Providers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Providers\NearYouRequest;
use App\Http\Requests\Providers\ReviewProviderRequest;
use App\Models\User;
use App\ProviderNetwork\AgentDirectory;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Providers near you (B16.3, ADR 0081): providers serving the agent's
 * cities, reviews from agents who worked with them, and Request to
 * connect in two taps.
 */
final class AgentDirectoryController extends Controller
{
    /**
     * Providers near you.
     *
     * Live, verified providers serving the cities where I work (from my deals and MLS listings in 12 months), near first, with ratings and whether I'm connected; and each category's count.
     *
     * @response array{cities: list<string>, categories: list<array{key: string, name: string, count: int}>, providers: list<array{id: int, name: string, category: string|null, category_key: string|null, photo_url: string|null, licence_verified: bool, areas: list<string>, response_hours: int|null, tier: string|null, rating: array{average: float|null, count: int}, near: bool, connection: string|null}>}
     */
    public function near(NearYouRequest $request, #[CurrentUser] User $user, AgentDirectory $directory): JsonResponse
    {
        return response()->json($directory->near($user, $request->category()));
    }

    /**
     * Reviews and my connection with a provider.
     *
     * @response array{data: array{rating: array{average: float|null, count: int}, reviews: list<array{id: string, rating: int, body: string|null, agent: string, at: string}>, connection: array{id: string, status: string}|null, can_connect: bool, can_review: bool, review_from: string|null, my_review: array{rating: int, body: string|null}|null}}
     */
    public function network(int $profile, #[CurrentUser] User $user, AgentDirectory $directory): JsonResponse
    {
        return response()->json(['data' => $directory->forProfile($user, $profile)]);
    }

    /**
     * Request to connect (agent).
     *
     * Connected at once, with a private chat in Messages. Contacts still need both to press Share. If the provider already asked, this accepts. Never counts toward the provider's monthly limit.
     *
     * @status 201
     *
     * @response array{data: array{id: string, side: 'agent'|'provider', with: string, provider: array{id: int, name: string, category: string|null, photo_url: string|null}, connected_at: string|null, i_shared: bool, they_shared: bool, contacts: array{email: string|null, phone: string|null}|null, conversation: string|null, blocked: bool}}
     */
    public function connect(int $profile, #[CurrentUser] User $user, AgentDirectory $directory): JsonResponse
    {
        return response()->json(['data' => $directory->connect($user, $profile)], 201);
    }

    /**
     * Review a provider.
     *
     * After 30 days connected; one review per agent per provider (a second replaces the first). No contact details in the note.
     *
     * @response array{data: array{rating: array{average: float|null, count: int}, reviews: list<array{id: string, rating: int, body: string|null, agent: string, at: string}>, connection: array{id: string, status: string}|null, can_connect: bool, can_review: bool, review_from: string|null, my_review: array{rating: int, body: string|null}|null}}
     */
    public function review(ReviewProviderRequest $request, int $profile, #[CurrentUser] User $user, AgentDirectory $directory): JsonResponse
    {
        return response()->json(['data' => $directory->review($user, $profile, $request->integer('rating'), $request->body())]);
    }
}
