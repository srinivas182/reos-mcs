<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Leads\OpenHouses;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Open houses on the web (B14.3a): start one, show its QR code, see who signed in. */
final class OpenHousePageController extends Controller
{
    public function index(#[CurrentUser] User $user, OpenHouses $houses): Response
    {
        $list = $houses->list($user);

        return Inertia::render('open-houses/index', [
            'houses' => $list,
            'visits' => $list === [] ? [] : $houses->visits($user, $list[0]['id']),
        ]);
    }
}
