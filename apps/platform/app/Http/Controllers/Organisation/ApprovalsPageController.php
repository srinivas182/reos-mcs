<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Brokerage\ApprovalsQueue;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Brokerage › Approvals on the web (A15.3, broker journey screens 7–12): the queue beside the item being reviewed. */
final class ApprovalsPageController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, ApprovalsQueue $queue): Response
    {
        return Inertia::render('organisation/approvals', ['queue' => $queue->for($user)]);
    }
}
