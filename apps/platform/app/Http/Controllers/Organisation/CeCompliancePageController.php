<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Brokerage\BrokerageAccess;
use App\Brokerage\CeCompliance;
use App\Brokerage\Sponsorships;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Brokerage › CE compliance on the web (A16.2): every agent's renewal and SAE, reminders, courses and sponsorship. */
final class CeCompliancePageController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, CeCompliance $ce, Sponsorships $sponsorships, BrokerageAccess $access): Response
    {
        [, $team] = $access->scope($user);

        return Inertia::render('organisation/ce-compliance', ['table' => $ce->table($user), 'sponsorships' => $team === null ? $sponsorships->board($user) : null]);
    }
}
