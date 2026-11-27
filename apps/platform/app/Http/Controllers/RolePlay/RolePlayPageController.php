<?php

declare(strict_types=1);

namespace App\Http\Controllers\RolePlay;

use App\Http\Controllers\Controller;
use App\Http\Requests\RolePlay\SayRequest;
use App\Http\Requests\RolePlay\StartRolePlayRequest;
use App\Models\User;
use App\RolePlay\RolePlays;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Role-play and the skills assessment on the web (A14.1a; agent screen 7). */
final class RolePlayPageController extends Controller
{
    public function index(#[CurrentUser] User $user, RolePlays $plays): Response
    {
        return Inertia::render('role-play/index', $plays->overview($user));
    }

    public function show(string $rolePlay, #[CurrentUser] User $user, RolePlays $plays): Response
    {
        return Inertia::render('role-play/show', ['session' => $plays->show($user, $rolePlay)]);
    }

    public function start(StartRolePlayRequest $request, #[CurrentUser] User $user, RolePlays $plays): RedirectResponse
    {
        $play = $plays->start($user, $request->string('type')->toString(), $request->string('persona')->toString());

        return to_route('role-play.show', $play->uuid);
    }

    public function assess(#[CurrentUser] User $user, RolePlays $plays): RedirectResponse
    {
        return to_route('role-play.show', $plays->assess($user)->uuid);
    }

    public function say(SayRequest $request, string $rolePlay, #[CurrentUser] User $user, RolePlays $plays): RedirectResponse
    {
        $plays->say($user, $rolePlay, $request->string('text')->toString());

        return back();
    }

    public function finish(string $rolePlay, #[CurrentUser] User $user, RolePlays $plays): RedirectResponse
    {
        $plays->finish($user, $rolePlay);

        return back();
    }
}
