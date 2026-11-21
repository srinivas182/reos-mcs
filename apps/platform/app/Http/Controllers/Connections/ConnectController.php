<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connections;

use App\Connections\HistoryRange;
use App\Http\Controllers\Controller;
use App\Models\OAuthAuthorization;
use App\Models\User;
use App\Services\Connections\ConnectionAuthorizer;
use App\Services\Connections\Connections;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** "Connect Google Calendar" on the website: off to the tool's sign-in. */
final class ConnectController extends Controller
{
    public function __invoke(Request $request, string $tool, #[CurrentUser] User $user, Connections $connections, ConnectionAuthorizer $authorizer): RedirectResponse|Response
    {
        $owner = $connections->ownerFor($user, $tool);

        if (! $connections->available($tool) || $owner === null) {
            return to_route('dashboard')->with('connection', ['outcome' => ConnectionAuthorizer::OUTCOME_FAILED, 'integration' => $tool, 'reason' => 'connections.errors.unavailable']);
        }

        $history = HistoryRange::tryFrom($request->string('history')->toString()) ?? HistoryRange::DEFAULT;

        return redirect()->away($authorizer->start($user, $tool, $owner[0], $owner[1], OAuthAuthorization::RETURN_WEB, $history));
    }
}
