<?php

declare(strict_types=1);

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Mls\MyListings;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** My listings on the web (B10.1): beside Deals in the agent's sidebar. */
final class ListingsPageController extends Controller
{
    public function index(#[CurrentUser] User $user, MyListings $listings): Response
    {
        return Inertia::render('listings/index', ['listings' => $listings->overview($user)]);
    }
}
