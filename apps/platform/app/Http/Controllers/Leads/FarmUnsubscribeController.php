<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Leads\FarmUpdates;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Unsubscribing from an agent's market updates (B14.3c): the link in each
 * email is signed. A mail app's one-click unsubscribe POSTs to it (RFC
 * 8058); a person opening it sees a button first, so link scanners don't
 * unsubscribe anyone.
 */
final class FarmUnsubscribeController extends Controller
{
    public function show(Request $request): View
    {
        $agent = User::query()->find($request->integer('agent')) ?? throw new NotFoundHttpException;

        return view('leads.unsubscribe', ['agent' => (string) $agent->name, 'action' => $request->fullUrl(), 'done' => false]);
    }

    public function store(Request $request, FarmUpdates $farm): View|Response
    {
        $farm->unsubscribe($request->integer('agent'), $request->string('e')->toString());
        $agent = User::query()->find($request->integer('agent'));

        return $request->has('List-Unsubscribe') ? response()->noContent() : view('leads.unsubscribe', ['agent' => (string) $agent?->name, 'action' => null, 'done' => true]);
    }
}
