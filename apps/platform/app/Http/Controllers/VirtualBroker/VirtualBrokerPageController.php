<?php

declare(strict_types=1);

namespace App\Http\Controllers\VirtualBroker;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\VirtualBroker\VirtualBroker;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The virtual broker on the web (A12.1, ADR 0047; agent web screen 16):
 * the conversation list, answers with sources, escalated questions with
 * their status and the broker's answer labelled. Asking and sending to the
 * broker use the same JSON actions as the API.
 */
final class VirtualBrokerPageController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, VirtualBroker $broker): Response
    {
        $open = $request->query('question');

        return Inertia::render('virtual-broker/index', [
            'broker' => $broker->forAgent($user),
            'open' => is_string($open) && preg_match('/^[0-9a-f-]{36}$/', $open) === 1 ? $open : null,
        ]);
    }
}
