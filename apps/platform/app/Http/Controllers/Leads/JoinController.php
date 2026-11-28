<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Leads\Invites;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The invite link (B14.3b): "Maria G. invited you to REOS". Remembers the
 * code for signing up on the web, and shows it to type in the app.
 */
final class JoinController extends Controller
{
    public function show(Request $request, string $code, Invites $invites): View
    {
        $inviter = $invites->inviter($code) ?? throw new NotFoundHttpException;

        if ($request->user() === null) {
            $request->session()->put('invite', $inviter->invite_code);
        }

        $first = (preg_split('/\s+/u', trim((string) $inviter->name)) ?: [''])[0];

        return view('leads.join', ['name' => $first, 'code' => (string) $inviter->invite_code, 'signedIn' => $request->user() !== null]);
    }
}
