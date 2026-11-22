<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sessions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sessions\ScheduleSessionRequest;
use App\Http\Requests\Sessions\SessionNotesRequest;
use App\Messaging\Contacts;
use App\Models\User;
use App\Models\VideoSession;
use App\Video\LiveKit;
use App\Video\SessionPresenter;
use App\Video\VideoSessions;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Sessions (B6.3): the list, scheduling, the room (screen 15) and notes (screen 16). */
final class SessionsController extends Controller
{
    public function index(#[CurrentUser] User $user, VideoSessions $sessions, Contacts $contacts): Response
    {
        $canHost = $sessions->canHost($user);

        return Inertia::render('sessions/index', [
            'sessions' => $sessions->for($user)->map(fn (VideoSession $s): array => SessionPresenter::present($s, $user))->values()->all(),
            'canHost' => $canHost,
            'contacts' => $canHost ? $contacts->for($user) : [],
            'lengths' => VideoSessions::LENGTHS,
        ]);
    }

    public function store(ScheduleSessionRequest $request, #[CurrentUser] User $user, VideoSessions $sessions): RedirectResponse
    {
        $session = $sessions->schedule($user, $request->plan());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('sessions.scheduled')]);

        return to_route('sessions.show', $session);
    }

    public function show(VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions, LiveKit $livekit): Response
    {
        $sessions->ensureParticipant($session, $user);
        $session->load(['host', 'participants.user']);

        return Inertia::render('sessions/show', [
            'session' => SessionPresenter::present($session, $user),
            'simulated' => $livekit->simulated(),
        ]);
    }

    /** Called by the room when the person taps Join; the token never sits in the page. */
    public function join(VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        return response()->json($sessions->join($session, $user));
    }

    public function end(VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): RedirectResponse
    {
        $sessions->end($session, $user);

        return to_route('sessions.show', $session);
    }

    public function cancel(VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): RedirectResponse
    {
        $sessions->cancel($user, $session);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('sessions.cancelled')]);

        return to_route('sessions.index');
    }

    public function notes(SessionNotesRequest $request, VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): RedirectResponse
    {
        $sessions->saveNotes($session, $user, $request->notes());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('sessions.notes.saved')]);

        return back();
    }
}
