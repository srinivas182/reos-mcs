<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\PageLeadRequest;
use App\Http\Requests\Leads\VisitorRequest;
use App\Leads\OpenHouses;
use App\Leads\PageLeads;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lead capture without an account (B14.3a): the open house sign-in page a
 * visitor reaches from the QR code, and the question form on the agent's
 * page. Plain server HTML.
 */
final class PublicLeadsController extends Controller
{
    public function openHouse(string $code, OpenHouses $houses): View
    {
        $house = $houses->byCode($code) ?? throw new NotFoundHttpException;
        $view = $houses->publicView($house);
        app()->setLocale($view['language']);

        return view('leads.open-house', ['house' => $view, 'code' => $house->code, 'timelines' => OpenHouses::TIMELINES, 'done' => session('signed_in', false)]);
    }

    public function signIn(string $code, VisitorRequest $request, OpenHouses $houses): RedirectResponse
    {
        $house = $houses->byCode($code) ?? throw new NotFoundHttpException;
        $houses->signIn($house, $request->visitor());

        return to_route('open-house.show', $house->code)->with('signed_in', true);
    }

    public function qr(string $code, OpenHouses $houses): Response
    {
        $house = $houses->byCode($code) ?? throw new NotFoundHttpException;

        return response($houses->qr($house), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function contact(string $handle, PageLeadRequest $request, PageLeads $leads): RedirectResponse
    {
        $leads->send($handle, [
            'name' => $request->string('name')->toString(),
            'email' => $request->filled('email') ? $request->string('email')->toString() : null,
            'phone' => $request->filled('phone') ? $request->string('phone')->toString() : null,
            'message' => $request->string('message')->toString(),
            'has_agent' => $request->boolean('has_agent'),
        ]);

        return redirect()->to(route('agent-pages.show', $handle).'#contact')->with('lead_sent', true);
    }
}
