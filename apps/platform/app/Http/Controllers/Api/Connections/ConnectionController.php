<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Connections;

use App\Connections\ConnectorRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Connections\StartConnectionRequest;
use App\Http\Requests\Connections\UpdateConnectionRequest;
use App\Http\Resources\Connections\ConnectionResource;
use App\Models\Connection;
use App\Models\OAuthAuthorization;
use App\Models\User;
use App\Services\Catalogue\IntegrationCatalogue;
use App\Services\Connections\ConnectionAuthorizer;
use App\Services\Connections\Connections;
use App\Services\Connections\ConnectionSettings;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Your connections to outside tools (B3.1, ADR 0013). */
final class ConnectionController extends Controller
{
    /**
     * List connections.
     *
     * Yours, and your active organisation's when your role there manages it.
     */
    public function index(#[CurrentUser] User $user, Connections $connections): AnonymousResourceCollection
    {
        return ConnectionResource::collection($connections->visibleTo($user));
    }

    /**
     * Start connecting a tool.
     *
     * Returns the tool's sign-in address. Open it in a secure browser sheet;
     * after signing in, the tool comes back to REOS, which returns to
     * `reos://connections?outcome=connected|cancelled|failed&integration=…`.
     *
     * Tools connected with the person's own API key (Follow Up Boss) take
     * `api_key` instead: the key is checked with the tool at once and the
     * new connection's id comes back (422 when the tool refuses it).
     *
     * @response array{authorization_url: string|null, connection_id: int|null}
     */
    public function store(StartConnectionRequest $request, #[CurrentUser] User $user, Connections $connections, ConnectionAuthorizer $authorizer, IntegrationCatalogue $catalogue): JsonResponse
    {
        $integration = $request->string('integration')->toString();
        $owner = $connections->ownerFor($user, $integration);

        // The catalogue decides: status, availability, beta and organisation hiding.
        if (! $catalogue->canConnect($user, $integration) || $owner === null) {
            return response()->json(['message' => __('connections.errors.unavailable')], 422);
        }

        // Key-based tools (Follow Up Boss, B7.2): checked with the tool now, no sign-in page.
        if (ConnectorRegistry::usesApiKey($integration)) {
            $key = $request->string('api_key')->toString();

            if ($key === '') {
                return response()->json(['message' => __('connections.errors.keyNeeded'), 'errors' => ['api_key' => [__('connections.errors.keyNeeded')]]], 422);
            }

            $result = $authorizer->connectWithKey($user, $integration, $owner[0], $owner[1], $key, $request->history());

            return $result['connection'] === null
                ? response()->json(['message' => __((string) $result['reason']), 'errors' => ['api_key' => [__((string) $result['reason'])]]], 422)
                : response()->json(['authorization_url' => null, 'connection_id' => $result['connection']->id]);
        }

        return response()->json([
            'connection_id' => null,
            'authorization_url' => $authorizer->start($user, $integration, $owner[0], $owner[1], OAuthAuthorization::RETURN_APP, $request->history()),
        ]);
    }

    /**
     * Sync now.
     *
     * Queues a sync of every stream; the list shows the new time when it's done.
     *
     * @response 202 array{queued: bool}
     */
    public function sync(#[CurrentUser] User $user, Connection $connection, Connections $connections): JsonResponse
    {
        abort_unless($connections->canManage($user, $connection), 404);

        if (! $connections->syncNow($connection)) {
            return response()->json(['message' => __('connections.errors.unavailable')], 422);
        }

        return response()->json(['queued' => true], 202);
    }

    /**
     * Change what syncs.
     *
     * A new history range syncs again from its start; nothing already synced
     * is lost. Calendars also take which calendars to read (full, busy only
     * or off) and which one REOS's own events go to (screen 16).
     */
    public function update(UpdateConnectionRequest $request, #[CurrentUser] User $user, Connection $connection, ConnectionSettings $settings): ConnectionResource
    {
        return new ConnectionResource($settings->save($user, $connection, $request)->fresh());
    }

    /**
     * Disconnect.
     *
     * REOS asks the tool to forget its access, then deletes the tokens and
     * everything it synced from this account.
     */
    public function destroy(#[CurrentUser] User $user, Connection $connection, Connections $connections): Response
    {
        abort_unless($connections->canManage($user, $connection), 404);

        $connections->disconnect($connection, $user);

        return response()->noContent();
    }
}
