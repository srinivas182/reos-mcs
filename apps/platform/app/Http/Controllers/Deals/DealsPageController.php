<?php

declare(strict_types=1);

namespace App\Http\Controllers\Deals;

use App\Deals\DealAccess;
use App\Deals\DealViews;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Deals board (agent web screen 14, B9.1): columns by stage, cards with
 * property, side, price and the next deadline; open a deal in the panel.
 * Deals from a tool are read-only; REOS deals drag between stages.
 */
final class DealsPageController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, DealAccess $access, DealViews $views): Response
    {
        $access->allow($user);
        $open = $request->query('deal');
        $selected = is_string($open) ? Deal::query()->where('user_id', $user->id)->where('uuid', $open)->with('dates')->first() : null;

        return Inertia::render('deals/index', [
            'board' => $views->board($user),
            'selected' => $selected === null ? null : $views->show($selected, $user),
        ]);
    }

    /** Connections › Transactions (Connections screen 17, B9.1b): deals from the agent's tools, read-only. */
    public function transactions(#[CurrentUser] User $user, DealAccess $access, DealViews $views): Response
    {
        $access->allow($user);

        return Inertia::render('connections/transactions', ['deals' => $views->transactions($user)]);
    }
}
