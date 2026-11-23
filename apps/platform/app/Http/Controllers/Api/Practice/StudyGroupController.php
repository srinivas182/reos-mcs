<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Practice;

use App\Http\Controllers\Controller;
use App\Models\StudyGroup;
use App\Models\User;
use App\Practice\Challenges;
use App\Practice\PracticeAccess;
use App\Practice\StudyGroups;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Study groups (A9.3c, candidate screen 52): private groups of up to 20,
 * opt-in from Profile, joined by invite link, with a weekly leaderboard
 * (first names only).
 */
final class StudyGroupController extends Controller
{
    /**
     * My study groups.
     *
     * Whether study groups are on, and each group with this week's leaderboard.
     *
     * @response array{data: array{opt_in: bool, groups: list<array{id: int, uuid: string, name: string, link: string, school: string|null, members: int, max: int, mine: bool, leaderboard: list<array{first_name: string, questions: int, accuracy: int|null, mine: bool}>, week_start: string}>}}
     */
    public function index(#[CurrentUser] User $user, PracticeAccess $access, StudyGroups $groups): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => ['opt_in' => $user->study_groups_opt_in, 'groups' => $groups->mine($user)]]);
    }

    /**
     * Turn study groups on or off.
     *
     * Off leaves every group.
     *
     * @response array{data: array{opt_in: bool, groups: list<array{id: int, uuid: string, name: string, link: string, school: string|null, members: int, max: int, mine: bool, leaderboard: list<array{first_name: string, questions: int, accuracy: int|null, mine: bool}>, week_start: string}>}}
     */
    public function optIn(Request $request, #[CurrentUser] User $user, PracticeAccess $access, StudyGroups $groups): JsonResponse
    {
        $access->allow($user);
        $groups->optIn($user, $request->validate(['opt_in' => ['required', 'boolean']])['opt_in'] === true);

        return $this->index($user, $access, $groups);
    }

    /**
     * Start a study group.
     *
     * I'm its first member; share its invite link.
     *
     * @status 201
     *
     * @response array{data: array{id: int, uuid: string, name: string, link: string, school: string|null, members: int, max: int, mine: bool, leaderboard: list<array{first_name: string, questions: int, accuracy: int|null, mine: bool}>, week_start: string}}
     */
    public function store(Request $request, #[CurrentUser] User $user, PracticeAccess $access, StudyGroups $groups): JsonResponse
    {
        $access->allow($user);
        $name = $request->validate(['name' => ['required', 'string', 'min:2', 'max:80']])['name'];

        return response()->json(['data' => $groups->show($groups->create($user, is_string($name) ? $name : ''), $user)], 201);
    }

    /**
     * A study group from its invite link.
     *
     * Its name and size; the leaderboard once I'm a member.
     *
     * @response array{data: array{id: int, uuid: string, name: string, link: string, school: string|null, members: int, max: int, mine: bool, leaderboard: list<array{first_name: string, questions: int, accuracy: int|null, mine: bool}>, week_start: string}}
     */
    public function show(string $group, #[CurrentUser] User $user, PracticeAccess $access, StudyGroups $groups): JsonResponse
    {
        $access->allow($user);
        $found = StudyGroup::query()->where('uuid', $group)->firstOrFail();

        return response()->json(['data' => $groups->show($found, $user, $groups->isMember($user, $found))]);
    }

    /**
     * Join a study group.
     *
     * Needs study groups on in Profile; up to 20 members.
     *
     * @response array{data: array{id: int, uuid: string, name: string, link: string, school: string|null, members: int, max: int, mine: bool, leaderboard: list<array{first_name: string, questions: int, accuracy: int|null, mine: bool}>, week_start: string}}
     */
    public function join(string $group, #[CurrentUser] User $user, PracticeAccess $access, StudyGroups $groups): JsonResponse
    {
        $access->allow($user);
        $found = StudyGroup::query()->where('uuid', $group)->firstOrFail();
        $groups->join($user, $found);

        return response()->json(['data' => $groups->show($found, $user)]);
    }

    /** Leave a study group. */
    public function leave(string $group, #[CurrentUser] User $user, PracticeAccess $access, StudyGroups $groups): JsonResponse
    {
        $access->allow($user);
        $groups->leave($user, StudyGroup::query()->where('uuid', $group)->firstOrFail());

        return response()->json(null, 204);
    }

    /**
     * Challenge the group.
     *
     * The same 10 questions for every member; they're told.
     *
     * @status 201
     *
     * @response array{data: array{uuid: string, link: string, from: string, mine: bool, questions: int, expires_at: string, expired: bool, attempt_id: int|null, submitted: bool, entries: list<array{first_name: string, correct: int, total: int, hints: int, points: int, mine: bool, finished: bool}>}}
     */
    public function challenge(string $group, #[CurrentUser] User $user, PracticeAccess $access, StudyGroups $groups, Challenges $challenges): JsonResponse
    {
        $access->allow($user);
        $found = StudyGroup::query()->where('uuid', $group)->firstOrFail();

        return response()->json(['data' => $challenges->board($groups->challenge($user, $found), $user)], 201);
    }
}
