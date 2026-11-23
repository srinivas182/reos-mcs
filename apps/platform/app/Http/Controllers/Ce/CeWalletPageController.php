<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ce;

use App\Http\Controllers\Controller;
use App\Licences\Ce\CeWallet;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** The CE wallet on the web (A11.2; agent screens 103, 105, 106 in the web shell). */
final class CeWalletPageController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, CeWallet $wallet): Response
    {
        return Inertia::render('ce/index', ['wallets' => $wallet->for($user)]);
    }
}
