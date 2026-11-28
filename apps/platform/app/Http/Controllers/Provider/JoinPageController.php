<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\ProviderNetwork\ProviderJoining;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Join the provider network on the web (B15.1, provider screen 2): the category grid and its profile fields. */
final class JoinPageController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, ProviderJoining $joining): Response
    {
        return Inertia::render('provider/join', ['categories' => $joining->categories('TX'), 'joining' => $joining->profile($user)]);
    }
}
