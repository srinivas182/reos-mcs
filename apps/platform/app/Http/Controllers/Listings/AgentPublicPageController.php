<?php

declare(strict_types=1);

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Mls\AgentPages;
use Inertia\Inertia;
use Inertia\Response;

/** An agent's public page (B10.2b): open to anyone once the agent publishes it. */
final class AgentPublicPageController extends Controller
{
    public function show(string $handle, AgentPages $pages): Response
    {
        $page = $pages->public($handle);
        abort_if($page === null, 404);

        return Inertia::render('public/agent', ['page' => $page]);
    }
}
