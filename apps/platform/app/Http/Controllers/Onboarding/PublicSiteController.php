<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public site (web journey screen 1): the reos.com home page and a landing
 * page for each audience. Server-rendered for search and AI answer engines.
 */
final class PublicSiteController extends Controller
{
    /** Audiences in the top navigation, in journey order. */
    public const AUDIENCES = ['candidates', 'agents', 'brokerages', 'schools', 'associations', 'service-providers'];

    public function home(): Response
    {
        return Inertia::render('welcome');
    }

    public function audience(string $audience): Response
    {
        return Inertia::render('public/audience', ['audience' => $audience]);
    }
}
