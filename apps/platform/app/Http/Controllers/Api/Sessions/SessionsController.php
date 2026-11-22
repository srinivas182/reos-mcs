<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sessions\ScheduleSessionRequest;
use App\Http\Requests\Sessions\SessionNotesRequest;
use App\Messaging\Contacts;
use App\Models\User;
use App\Models\VideoSession;
use App\Video\SessionPresenter;
use App\Video\VideoSessions;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SessionsController extends Controller
{
    /**
     * Your sessions (B6.3).
     *
     * Coming up and the last 30 days. Hosts (instructors and mentors) also
     * get the people they can invite.
     *
     * @response array{sessions: list<array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null}>, can_host: bool, contacts: list<array{id: int, name: string, role: string, organisation: string}>, lengths: list<int>}
     */
    public function index(#[CurrentUser] User $user, VideoSessions $sessions, Contacts $contacts): JsonResponse
    {
        $canHost = $sessions->canHost($user);

        return response()->json([
            'sessions' => $sessions->for($user)->map(fn (VideoSession $s): array => SessionPresenter::present($s, $user))->values()->all(),
            'can_host' => $canHost,
            'contacts' => $canHost ? $contacts->for($user) : [],
            'lengths' => VideoSessions::LENGTHS,
        ]);
    }

    /**
     * Schedule a session (B6.3).
     *
     * Instructors and mentors only, with people they work with.
     *
     * @status 201
     *
     * @response array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null}
     */
    public function store(ScheduleSessionRequest $request, #[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        $session = $sessions->schedule($user, $request->plan());
        $session->load(['host', 'participants.user']);

        return response()->json(SessionPresenter::present($session, $user), 201);
    }

    /**
     * A session (B6.3).
     *
     * @response array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null}
     */
    public function show(VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        $sessions->ensureParticipant($session, $user);
        $session->load(['host', 'participants.user']);

        return response()->json(SessionPresenter::present($session, $user));
    }

    /**
     * Join the room (B6.3).
     *
     * From five minutes before the start until the end. Returns the LiveKit
     * server and a token for this person and room that stops working at the
     * end; `simulated` is true where LiveKit isn't set up (outside production).
     *
     * @response array{simulated: bool, url: string|null, token: string|null, ends_at: string}
     */
    public function join(VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        return response()->json($sessions->join($session, $user));
    }

    /**
     * End the session for everyone (B6.3). Host only.
     *
     * @response array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null}
     */
    public function end(VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        $sessions->end($session, $user);

        return $this->show($session->refresh(), $user, $sessions);
    }

    /**
     * Cancel a session (B6.3). Host only, before it starts.
     *
     * @response array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null}
     */
    public function cancel(VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        $sessions->cancel($user, $session);

        return $this->show($session->refresh(), $user, $sessions);
    }

    /**
     * Save the session notes (B6.3, screen 16).
     *
     * Shared by everyone in the session.
     *
     * @response array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null}
     */
    public function notes(SessionNotesRequest $request, VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        $sessions->saveNotes($session, $user, $request->notes());

        return $this->show($session->refresh(), $user, $sessions);
    }
}
