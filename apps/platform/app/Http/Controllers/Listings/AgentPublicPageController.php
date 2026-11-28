<?php

declare(strict_types=1);

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Mls\AgentCards;
use App\Mls\AgentPages;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * An agent's public page (B10.2b) and digital card (B14.1, ADR 0064): open
 * to anyone once the agent publishes it, rendered on the server as plain
 * HTML so people, search engines and answer engines read it without
 * JavaScript.
 */
final class AgentPublicPageController extends Controller
{
    public function show(string $handle, AgentPages $pages, AgentCards $cards): View
    {
        $page = $pages->public($handle);
        abort_if($page === null, 404);
        $faqs = $cards->faqs($page);
        app()->setLocale($page['language']);

        return view('public.agent', ['page' => $page, 'faqs' => $faqs, 'schema' => $cards->schema($page, $faqs)]);
    }

    public function card(string $handle, AgentPages $pages, AgentCards $cards): View
    {
        $page = $pages->public($handle);
        abort_if($page === null, 404);
        app()->setLocale($page['language']);

        return view('public.card', ['page' => $page, 'qr' => $cards->qr($page['url'], 240)]);
    }

    public function vcard(string $handle, AgentPages $pages, AgentCards $cards): Response
    {
        $page = $pages->public($handle);
        abort_if($page === null, 404);

        return response($cards->vcard($page), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$page['handle'].'.vcf"',
        ]);
    }

    public function qr(string $handle, AgentPages $pages, AgentCards $cards): Response
    {
        $page = $pages->public($handle);
        abort_if($page === null, 404);

        return response($cards->qr($page['url']), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }
}
