<?php

declare(strict_types=1);

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Mls\ListingPosts;
use App\Models\User;
use App\Social\SocialAccounts;
use App\Social\SocialPosts;
use App\Studio\Studio;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Content studio (B11, agent web screen 17; with screens 108 and 169):
 * compose a post (or start from a listing post), see the broker name and
 * checks, choose accounts, then publish now or schedule; and my posts with
 * their results; and make content from a listing, a sale, a market update
 * or the agent's words (B12.1).
 */
final class StudioPageController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, SocialAccounts $accounts, SocialPosts $posts, ListingPosts $listingPosts, Studio $studio): Response
    {
        $listing = $request->query('listing');

        return Inertia::render('studio/index', [
            'accounts' => $accounts->for($user),
            'posts' => $posts->list($user),
            // Start from a listing post (B10.2): its caption and the photos the MLS allows.
            // Make content (B12.1): sources, cost and what was made lately.
            'maker' => $studio->home($user),
            'listing' => is_string($listing) && preg_match('/^[0-9a-f-]{36}$/', $listing) === 1 ? $listingPosts->draft($user, $listing) : null,
        ]);
    }
}
