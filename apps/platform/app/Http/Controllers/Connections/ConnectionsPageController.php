<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connections;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\ToolRequestRequest;
use App\Http\Requests\Connections\UpdateConnectionRequest;
use App\Http\Resources\Catalogue\CatalogueItemResource;
use App\Http\Resources\Connections\ConnectionResource;
use App\Integrations\Catalogue;
use App\Models\Connection;
use App\Models\IntegrationInterest;
use App\Models\User;
use App\Services\Catalogue\IntegrationCatalogue;
use App\Services\Connections\Connections;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connections on the website (web journey screen 19): the tools this person
 * may use, as the super admin's catalogue allows, with Connect, Manage,
 * Join beta, Notify me and Request a tool. Connect itself goes through
 * ConnectController (the tool's own sign-in page).
 */
final class ConnectionsPageController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, IntegrationCatalogue $catalogue, Connections $connections): Response
    {
        return Inertia::render('connections/index', [
            'tools' => CatalogueItemResource::collection($catalogue->forUser($user))->resolve($request),
            'connections' => ConnectionResource::collection($connections->visibleTo($user))->resolve($request),
        ]);
    }

    /** "Notify me" or "Join beta". The page confirms it with Undo. */
    public function interest(string $tool, #[CurrentUser] User $user, IntegrationCatalogue $catalogue): RedirectResponse
    {
        if (! $catalogue->askFor($user, $tool)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('catalogue.errors.noInterest')]);
        }

        return back();
    }

    /** Undo "Notify me" or "Join beta". */
    public function forget(string $tool, #[CurrentUser] User $user, IntegrationCatalogue $catalogue): RedirectResponse
    {
        $catalogue->setInterest($user, $tool, IntegrationInterest::NOTIFY, false);

        return back();
    }

    /** "Request a tool". */
    public function request(ToolRequestRequest $request, #[CurrentUser] User $user, IntegrationCatalogue $catalogue): RedirectResponse
    {
        $catalogue->requestTool($user, $request->string('name')->toString(), $request->filled('note') ? $request->string('note')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('connections.request.sent')]);

        return back();
    }

    public function sync(#[CurrentUser] User $user, Connection $connection, Connections $connections): RedirectResponse
    {
        abort_unless($connections->canManage($user, $connection), 404);

        $queued = $connections->syncNow($connection);
        Inertia::flash('toast', $queued
            ? ['type' => 'success', 'message' => __('connections.detail.syncQueued')]
            : ['type' => 'error', 'message' => __('connections.errors.unavailable')]);

        return back();
    }

    /** How far back it syncs (Manage › What to sync). */
    public function update(UpdateConnectionRequest $request, #[CurrentUser] User $user, Connection $connection, Connections $connections): RedirectResponse
    {
        abort_unless($connections->canManage($user, $connection), 404);

        $connections->changeHistory($connection, $request->history());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('connections.settings.saved')]);

        return back();
    }

    public function destroy(#[CurrentUser] User $user, Connection $connection, Connections $connections, Catalogue $register): RedirectResponse
    {
        abort_unless($connections->canManage($user, $connection), 404);

        $name = $register->find($connection->integration_id)->name ?? $connection->integration_id;
        $connections->disconnect($connection, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('connections.detail.disconnected', ['tool' => $name])]);

        return back();
    }
}
