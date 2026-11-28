<?php

declare(strict_types=1);

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Models\AgentPage;
use Illuminate\Http\Response;

/** The sitemap and robots.txt (B14.1): published agent pages, for search engines. */
final class SitemapController extends Controller
{
    /** Search engines read at most this many; enough for every Texas agent on REOS for years. */
    private const LIMIT = 45000;

    public function sitemap(): Response
    {
        $pages = AgentPage::query()->where('published', true)->orderBy('id')->limit(self::LIMIT)->get(['handle', 'updated_at']);

        return response()->view('public.sitemap', ['pages' => $pages], 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nDisallow:\n\nSitemap: ".route('sitemap')."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
