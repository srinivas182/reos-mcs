<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\ToolRequestRequest;
use App\Http\Resources\Catalogue\CatalogueItemResource;
use App\Models\IntegrationInterest;
use App\Models\User;
use App\Services\Catalogue\IntegrationCatalogue;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** The Connections library (B3.2): what you can connect, from the super admin's catalogue. */
final class CatalogueController extends Controller
{
    /**
     * List the catalogue.
     *
     * The tools you may see, with what you can do with each. A status change
     * in the back office shows here at once, with no app release.
     */
    public function index(#[CurrentUser] User $user, IntegrationCatalogue $catalogue): AnonymousResourceCollection
    {
        return CatalogueItemResource::collection($catalogue->forUser($user));
    }

    /**
     * Notify me / Join beta.
     *
     * Counted as demand for the tool, so the most wanted are built first.
     *
     * @response 204
     */
    public function interest(string $tool, #[CurrentUser] User $user, IntegrationCatalogue $catalogue): Response|JsonResponse
    {
        if (! $catalogue->askFor($user, $tool)) {
            return response()->json(['message' => __('catalogue.errors.noInterest')], 422);
        }

        return response()->noContent();
    }

    /** Undo "Notify me" or "Join beta". */
    public function forget(string $tool, #[CurrentUser] User $user, IntegrationCatalogue $catalogue): Response
    {
        $catalogue->setInterest($user, $tool, IntegrationInterest::NOTIFY, false);

        return response()->noContent();
    }

    /**
     * My tool isn't listed.
     *
     * @response 201 array{received: bool}
     */
    public function request(ToolRequestRequest $request, #[CurrentUser] User $user, IntegrationCatalogue $catalogue): JsonResponse
    {
        $catalogue->requestTool($user, $request->string('name')->toString(), $request->filled('note') ? $request->string('note')->toString() : null);

        return response()->json(['received' => true], 201);
    }
}
