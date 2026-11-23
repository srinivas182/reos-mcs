<?php

declare(strict_types=1);

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\CmaRequest;
use App\Mls\AgentPages;
use App\Mls\Cmas;
use App\Mls\MyListings;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** My listings on the web (B10.1): beside Deals in the agent's sidebar; CMAs and the public page (B10.2b). */
final class ListingsPageController extends Controller
{
    public function index(#[CurrentUser] User $user, MyListings $listings, AgentPages $pages): Response
    {
        return Inertia::render('listings/index', ['listings' => $listings->overview($user), 'page' => $pages->settings($user)]);
    }

    /** A CMA for one of the agent's listings (B10.2b); printable as a page. */
    public function cma(CmaRequest $request, string $listing, #[CurrentUser] User $user, Cmas $cmas): Response
    {
        return Inertia::render('listings/cma', ['cma' => $cmas->for($user, $listing, $request->radius(), $request->months()), 'radii' => Cmas::RADII, 'ranges' => Cmas::MONTHS]);
    }
}
