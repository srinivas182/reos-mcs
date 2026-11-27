<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mentoring;

use App\Http\Controllers\Controller;
use App\Mentoring\MentorWorkspace;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The mentor's workspace (A13.1b, ADR 0053; mentor screens 22–24). Brokerage mentors only (404 otherwise). */
final class MentorWorkspaceController extends Controller
{
    /**
     * Today for mentors.
     *
     * Mentees, this week's sessions, who needs help (quiet for a week, no first session yet, behind on their plan), upcoming sessions and wrap-ups to send.
     *
     * @response array{data: array{brokerage: string, mentees: int, sessions_this_week: int, needs: list<array{id: int, name: string, goal: string, assigned: bool, signal: 'quiet'|'first_session'|'behind'|'ok', last_active: string|null, next_session: string|null}>, upcoming: list<array{uuid: string, agent: string, agent_id: int, starts_at: string, minutes: int, topic: string, status: string, session: string|null, summary: string|null, tasks_sent: bool}>, wrap_ups: list<array{uuid: string, agent: string, agent_id: int, starts_at: string, minutes: int, topic: string, status: string, session: string|null, summary: string|null, tasks_sent: bool}>}}
     */
    public function today(#[CurrentUser] User $user, MentorWorkspace $workspace): JsonResponse
    {
        return response()->json(['data' => $workspace->today($user)]);
    }

    /**
     * My mentees.
     *
     * Assigned by the brokerage, or who booked me (`filter`: all, assigned or booked); who needs help first.
     *
     * @response array{data: list<array{id: int, name: string, goal: string, assigned: bool, signal: 'quiet'|'first_session'|'behind'|'ok', last_active: string|null, next_session: string|null}>}
     */
    public function mentees(Request $request, #[CurrentUser] User $user, MentorWorkspace $workspace): JsonResponse
    {
        $request->validate(['filter' => ['nullable', 'in:all,assigned,booked']]);

        return response()->json(['data' => $workspace->mentees($user, $request->string('filter', 'all')->toString())]);
    }

    /**
     * One mentee.
     *
     * The plan's week and progress, this week's scorecard, deal counts, sessions together, goals set together and my private notes. Never their CRM contacts.
     *
     * @response array{data: array{mentee: array{id: int, name: string, goal: string, assigned: bool, signal: 'quiet'|'first_session'|'behind'|'ok', last_active: string|null, next_session: string|null}, plan: array{week: int, weeks: int, done: int, total: int}, scorecard: list<array{key: string, done: float|int, target: float|int}>, fix: string|null, deals: array{active: int, under_contract: int, closed: int}, sessions: list<array{uuid: string, agent: string, agent_id: int, starts_at: string, minutes: int, topic: string, status: string, session: string|null, summary: string|null, tasks_sent: bool}>, goals: list<array{id: int, title: string, due_date: string|null, done: bool}>, notes: list<array{id: string, body: string, created_at: string|null}>}}
     */
    public function mentee(int $agent, #[CurrentUser] User $user, MentorWorkspace $workspace): JsonResponse
    {
        return response()->json(['data' => $workspace->mentee($user, $agent)]);
    }

    /**
     * Add a private note.
     *
     * Seen by me only.
     *
     * @status 201
     *
     * @response array{data: array{id: string, body: string, created_at: string|null}}
     */
    public function addNote(Request $request, int $agent, #[CurrentUser] User $user, MentorWorkspace $workspace): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);
        $note = $workspace->addNote($user, $agent, $request->string('body')->toString());

        return response()->json(['data' => ['id' => $note->uuid, 'body' => $note->body, 'created_at' => $note->created_at?->toIso8601String()]], 201);
    }

    /**
     * Remove a note.
     *
     * Undo with restore.
     */
    public function removeNote(string $note, #[CurrentUser] User $user, MentorWorkspace $workspace): JsonResponse
    {
        $workspace->removeNote($user, $note);

        return response()->json(['data' => ['removed' => true]]);
    }

    /** Restore a removed note. */
    public function restoreNote(string $note, #[CurrentUser] User $user, MentorWorkspace $workspace): JsonResponse
    {
        $workspace->removeNote($user, $note, restore: true);

        return response()->json(['data' => ['removed' => false]]);
    }

    /**
     * Add a goal together.
     *
     * It becomes one of the mentee's to-dos, from me, on its date.
     *
     * @status 201
     *
     * @response array{data: array{id: int, title: string, due_date: string|null, done: bool}}
     */
    public function addGoal(Request $request, int $agent, #[CurrentUser] User $user, MentorWorkspace $workspace): JsonResponse
    {
        $request->validate(['title' => ['required', 'string', 'min:2', 'max:200'], 'due_date' => ['nullable', 'date_format:Y-m-d']]);
        $todo = $workspace->addGoal($user, $agent, $request->string('title')->toString(), $request->filled('due_date') ? $request->string('due_date')->toString() : null);

        return response()->json(['data' => ['id' => $todo->id, 'title' => $todo->title, 'due_date' => $todo->due_date?->toDateString(), 'done' => false]], 201);
    }
}
