<?php

declare(strict_types=1);

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\StudyGroup;
use App\Models\User;
use App\Practice\Challenges;
use App\Practice\PracticeAccess;
use App\Practice\StudyGroups;
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

        return Inertia::render('practice/challenges', [
            'challenges' => $challenges->mine($user),
            // Study groups (A9.3c): on from Profile.
            'opt_in' => $user->study_groups_opt_in,
            'groups' => app(StudyGroups::class)->mine($user),
        ]);
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

    /** A study group's invite link (A9.3c): signed out, its name and sign in; signed in, Join. */
    public function group(string $group, Request $request, PracticeAccess $access, StudyGroups $groups): Response
    {
        $found = StudyGroup::query()->where('uuid', $group)->firstOrFail();
        $user = $request->user();

        if (! $user instanceof User) {
            $request->session()->put('url.intended', $request->fullUrl());

            return Inertia::render('public/group', ['name' => $found->name, 'members' => $found->members()->count()]);
        }

        $access->allow($user);

        return Inertia::render('practice/group', ['group' => $groups->show($found, $user, $groups->isMember($user, $found)), 'opt_in' => $user->study_groups_opt_in]);
    }
}
