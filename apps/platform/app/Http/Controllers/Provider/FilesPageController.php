<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\Coordinators\DealShares;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** My files on the web (B17.1, provider screens 14–15): the coordinator's board and one file. */
final class FilesPageController extends Controller
{
    public function index(#[CurrentUser] User $user, DealShares $shares): Response
    {
        return Inertia::render('provider/files', ['board' => $shares->board($user), 'file' => null]);
    }

    public function show(string $share, #[CurrentUser] User $user, DealShares $shares): Response
    {
        return Inertia::render('provider/files', ['board' => $shares->board($user), 'file' => $shares->file($user, $share)]);
    }
}
