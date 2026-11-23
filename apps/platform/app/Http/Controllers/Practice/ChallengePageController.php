<?php

declare(strict_types=1);

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\User;
use App\Practice\Challenges;
use App\Practice\PracticeAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Challenges on the web (the phone's screen 52, A9.3b). The share link
 * `/challenges/{uuid}` opens for anyone: signed out, it says who sent it
 * (first name) and asks them to sign in or start; signed in, it's the
 * challenge with Accept and the board.
 */
final class ChallengePageController extends Controller
{
    public function index(Request $request, PracticeAccess $access, Challenges $challenges): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $access->allow($user);

        return Inertia::render('practice/challenges', ['challenges' => $challenges->mine($user)]);
    }

    public function show(string $challenge, Request $request, PracticeAccess $access, Challenges $challenges): Response
    {
        $found = Challenge::query()->with('user')->where('uuid', $challenge)->firstOrFail();
        $user = $request->user();

        if (! $user instanceof User) {
            $request->session()->put('url.intended', $request->fullUrl());

            return Inertia::render('public/challenge', [
                'from' => Challenges::firstName($found->user), 'questions' => count($found->question_ids), 'expired' => $found->expires_at->isPast(),
            ]);
        }

        $access->allow($user);
        $board = $challenges->board($found, $user);

        return Inertia::render('practice/challenge', ['challenge' => $challenges->takesPart($user, $found) ? $board : ['entries' => []] + $board]);
    }
}
