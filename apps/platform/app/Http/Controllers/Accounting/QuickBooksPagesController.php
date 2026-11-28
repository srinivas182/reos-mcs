<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Accounting\QuickBooksExports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CallbackRequest;
use App\Licences\Journey\BrokerOffers;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** QuickBooks on the web (B17.3, broker web journey: integrations): the page, and Intuit's redirect back. */
final class QuickBooksPagesController extends Controller
{
    public function index(#[CurrentUser] User $user, BrokerOffers $offers, QuickBooksExports $exports): Response
    {
        return Inertia::render('organisation/accounting', ['board' => $exports->board($offers->brokerageOf($user))]);
    }

    public function callback(CallbackRequest $request, #[CurrentUser] User $user, BrokerOffers $offers, QuickBooksExports $exports): RedirectResponse
    {
        $exports->callback($offers->brokerageOf($user), $user, $request->string('state')->toString(), $request->string('code')->toString(), $request->string('realmId')->toString());

        return to_route('accounting.index')->with('success', __('accounting.connected'));
    }
}
