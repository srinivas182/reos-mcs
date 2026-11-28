<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Connections\Drivers\DocuSign\DocuSignSimulator;
use App\Deals\ClientPortals;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ReviewRequest;
use App\Models\Connection;
use App\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The client's portal (B14.2, ADR 0065): one private link, no account.
 * Plain server HTML, so it opens fast on any phone.
 */
final class PortalController extends Controller
{
    public function show(string $token, Request $request, ClientPortals $portals): View
    {
        $portal = $portals->find($token) ?? throw new NotFoundHttpException;
        $signed = $request->query('signed');
        $view = $portals->view($portal, is_string($signed) ? $signed : null);
        app()->setLocale($view['language']);

        return view('portal.show', ['view' => $view, 'token' => $token, 'reviewed' => $request->session()->get('reviewed', false)]);
    }

    public function sign(string $token, string $signature, ClientPortals $portals): RedirectResponse
    {
        $portal = $portals->find($token) ?? throw new NotFoundHttpException;

        return redirect()->away($portals->sign($portal, $token, $signature));
    }

    public function review(string $token, ReviewRequest $request, ClientPortals $portals): RedirectResponse
    {
        $portal = $portals->find($token) ?? throw new NotFoundHttpException;
        $portals->review($portal, $request->integer('rating'), $request->filled('comment') ? $request->string('comment')->toString() : null, $request->boolean('publish'));

        return to_route('portal.show', $token)->with('reviewed', true);
    }

    /** DocuSign on demo data (B14.2): the signing view. */
    public function simulator(Request $request): View
    {
        return view('portal.simulator', ['query' => $request->only(['connection', 'envelope', 'client', 'return'])]);
    }

    /** DocuSign on demo data: "Finish" signs as the client and goes back to the portal. */
    public function simulatorSign(Request $request): RedirectResponse
    {
        $connection = app(Tenancy::class)->asPlatform(static fn (): Connection => Connection::query()->findOrFail($request->integer('connection')));
        app(Tenancy::class)->asPlatform(static fn () => DocuSignSimulator::signAs($connection, $request->string('envelope')->toString(), $request->string('client')->toString()));
        $return = $request->string('return')->toString();

        return redirect()->to(str_starts_with($return, url('/')) ? $return : url('/'));
    }
}
