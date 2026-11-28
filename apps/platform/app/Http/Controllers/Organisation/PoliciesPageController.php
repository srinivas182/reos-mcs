<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Brokerage\BrokerageAccess;
use App\Brokerage\BrokerageRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Brokerage › Policies on the web (A15.2): the manual, the advertising policy and the brokerage's own rules. */
final class PoliciesPageController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, BrokerageAccess $access, BrokerageRules $rules): Response
    {
        return Inertia::render('organisation/policies', ['policies' => $rules->view($access->brokerage($user), $user)]);
    }
}
