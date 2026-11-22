<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions;

use App\Enums\QuizTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sessions\RecordingAnswerRequest;
use App\Http\Requests\Sessions\ScheduleSessionRequest;
use App\Http\Requests\Sessions\SessionNotesRequest;
use App\Http\Requests\Sessions\ShareRequest;
use App\Messaging\Contacts;
use App\Models\User;
use App\Models\VideoRecording;
use App\Models\VideoSession;
use App\Privacy\ConsentMethod;
use App\Video\SessionPresenter;
use App\Video\SessionRecordings;
use App\Video\SessionShares;
use App\Video\VideoSessions;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SessionsController extends Controller
{
    /**
     * Your sessions (B6.3).
     *
     * Coming up and the last 30 days. Hosts (instructors and mentors) also
     * get the people they can invite.
     *
     * @response array{sessions: list<array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null, whiteboard: array{image_url: string|null, updated_at: string|null}, shares: list<array{id: int, kind: 'lesson'|'questions', at: string, by: string|null, lesson: array{id: int, title: string, course: string}|null, topic: string|null, questions: list<array{id: int, question: string, options: list<string>, correct: int, explanation: string, source: string}>}>, recordings: list<array{id: string, status: 'asking'|'declined'|'recording'|'stopping'|'ready'|'failed', requested_by: string|null, started_at: string|null, stopped_at: string|null, duration_seconds: int|null, expires_at: string|null, play_url: string|null, agreed: list<string>, waiting: list<string>, my_answer: bool|null}>}>, can_host: bool, contacts: list<array{id: int, name: string, role: string, organisation: string}>, lengths: list<int>}
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
     * @response array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null, whiteboard: array{image_url: string|null, updated_at: string|null}, shares: list<array{id: int, kind: 'lesson'|'questions', at: string, by: string|null, lesson: array{id: int, title: string, course: string}|null, topic: string|null, questions: list<array{id: int, question: string, options: list<string>, correct: int, explanation: string, source: string}>}>, recordings: list<array{id: string, status: 'asking'|'declined'|'recording'|'stopping'|'ready'|'failed', requested_by: string|null, started_at: string|null, stopped_at: string|null, duration_seconds: int|null, expires_at: string|null, play_url: string|null, agreed: list<string>, waiting: list<string>, my_answer: bool|null}>}
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
     * @response array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null, whiteboard: array{image_url: string|null, updated_at: string|null}, shares: list<array{id: int, kind: 'lesson'|'questions', at: string, by: string|null, lesson: array{id: int, title: string, course: string}|null, topic: string|null, questions: list<array{id: int, question: string, options: list<string>, correct: int, explanation: string, source: string}>}>, recordings: list<array{id: string, status: 'asking'|'declined'|'recording'|'stopping'|'ready'|'failed', requested_by: string|null, started_at: string|null, stopped_at: string|null, duration_seconds: int|null, expires_at: string|null, play_url: string|null, agreed: list<string>, waiting: list<string>, my_answer: bool|null}>}
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
     * @response array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null, whiteboard: array{image_url: string|null, updated_at: string|null}, shares: list<array{id: int, kind: 'lesson'|'questions', at: string, by: string|null, lesson: array{id: int, title: string, course: string}|null, topic: string|null, questions: list<array{id: int, question: string, options: list<string>, correct: int, explanation: string, source: string}>}>, recordings: list<array{id: string, status: 'asking'|'declined'|'recording'|'stopping'|'ready'|'failed', requested_by: string|null, started_at: string|null, stopped_at: string|null, duration_seconds: int|null, expires_at: string|null, play_url: string|null, agreed: list<string>, waiting: list<string>, my_answer: bool|null}>}
     */
    public function end(VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        $sessions->end($session, $user);

        return $this->show($session->refresh(), $user, $sessions);
    }

    /**
     * Cancel a session (B6.3). Host only, before it starts.
     *
     * @response array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null, whiteboard: array{image_url: string|null, updated_at: string|null}, shares: list<array{id: int, kind: 'lesson'|'questions', at: string, by: string|null, lesson: array{id: int, title: string, course: string}|null, topic: string|null, questions: list<array{id: int, question: string, options: list<string>, correct: int, explanation: string, source: string}>}>, recordings: list<array{id: string, status: 'asking'|'declined'|'recording'|'stopping'|'ready'|'failed', requested_by: string|null, started_at: string|null, stopped_at: string|null, duration_seconds: int|null, expires_at: string|null, play_url: string|null, agreed: list<string>, waiting: list<string>, my_answer: bool|null}>}
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
     * @response array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null, whiteboard: array{image_url: string|null, updated_at: string|null}, shares: list<array{id: int, kind: 'lesson'|'questions', at: string, by: string|null, lesson: array{id: int, title: string, course: string}|null, topic: string|null, questions: list<array{id: int, question: string, options: list<string>, correct: int, explanation: string, source: string}>}>, recordings: list<array{id: string, status: 'asking'|'declined'|'recording'|'stopping'|'ready'|'failed', requested_by: string|null, started_at: string|null, stopped_at: string|null, duration_seconds: int|null, expires_at: string|null, play_url: string|null, agreed: list<string>, waiting: list<string>, my_answer: bool|null}>}
     */
    public function notes(SessionNotesRequest $request, VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        $sessions->saveNotes($session, $user, $request->notes());

        return $this->show($session->refresh(), $user, $sessions);
    }

    /**
     * What the host can share in the room (B6.3b).
     *
     * Lessons live in the host's state and practice-question topics. Host only.
     *
     * @response array{lessons: list<array{id: int, title: string, course: string}>, topics: list<array{topic: string, count: int}>}
     */
    public function shareOptions(VideoSession $session, #[CurrentUser] User $user, SessionShares $shares): JsonResponse
    {
        return response()->json($shares->options($session, $user));
    }

    /**
     * Share a lesson or send practice questions (B6.3b).
     *
     * Host only, while the session is on. Everyone in it sees it at once.
     *
     * @status 201
     *
     * @response array{id: int, kind: 'lesson'|'questions', at: string, by: string|null, lesson: array{id: int, title: string, course: string}|null, topic: string|null, questions: list<array{id: int, question: string, options: list<string>, correct: int, explanation: string, source: string}>}
     */
    public function share(ShareRequest $request, VideoSession $session, #[CurrentUser] User $user, SessionShares $shares): JsonResponse
    {
        $share = $request->string('kind')->toString() === 'lesson'
            ? $shares->shareLesson($session, $user, $request->integer('lesson'))
            : $shares->shareQuestions($session, $user, QuizTopic::from($request->string('topic')->toString()));

        return response()->json(SessionShares::present($share, app()->getLocale()), 201);
    }

    /**
     * Leave the room (B6.3c).
     *
     * Recording only asks the people still in the room.
     *
     * @response array{left: bool}
     */
    public function leave(VideoSession $session, #[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        $sessions->leave($session, $user);

        return response()->json(['left' => true]);
    }

    /**
     * Ask to record (B6.3c). Host only.
     *
     * Everyone in the room is asked; it starts only when all of them agree.
     *
     * @status 201
     *
     * @response array{id: string, status: 'asking'|'declined'|'recording'|'stopping'|'ready'|'failed', requested_by: string|null, started_at: string|null, stopped_at: string|null, duration_seconds: int|null, expires_at: string|null, play_url: string|null, agreed: list<string>, waiting: list<string>, my_answer: bool|null}
     */
    public function record(VideoSession $session, #[CurrentUser] User $user, SessionRecordings $recordings): JsonResponse
    {
        $recording = $recordings->ask($session, $user, ConsentMethod::App);

        return response()->json($recordings->present($recording->refresh(), $user), 201);
    }

    /**
     * Agree or not to recording (B6.3c).
     *
     * Saved as a consent record. One "no" cancels the request; someone
     * joining while it records must agree first.
     *
     * @response array{id: string, status: 'asking'|'declined'|'recording'|'stopping'|'ready'|'failed', requested_by: string|null, started_at: string|null, stopped_at: string|null, duration_seconds: int|null, expires_at: string|null, play_url: string|null, agreed: list<string>, waiting: list<string>, my_answer: bool|null}
     */
    public function answerRecording(RecordingAnswerRequest $request, VideoSession $session, VideoRecording $recording, #[CurrentUser] User $user, SessionRecordings $recordings): JsonResponse
    {
        abort_unless($recording->video_session_id === $session->id, 404);
        $recordings->answer($recording, $user, $request->boolean('agreed'), ConsentMethod::App);

        return response()->json($recordings->present($recording->refresh(), $user));
    }

    /**
     * Stop recording (B6.3c). Anyone in the session.
     *
     * @response array{id: string, status: 'asking'|'declined'|'recording'|'stopping'|'ready'|'failed', requested_by: string|null, started_at: string|null, stopped_at: string|null, duration_seconds: int|null, expires_at: string|null, play_url: string|null, agreed: list<string>, waiting: list<string>, my_answer: bool|null}
     */
    public function stopRecording(VideoSession $session, VideoRecording $recording, #[CurrentUser] User $user, SessionRecordings $recordings): JsonResponse
    {
        abort_unless($recording->video_session_id === $session->id, 404);
        $recordings->stop($recording, $user);

        return response()->json($recordings->present($recording->refresh(), $user));
    }

    /** Delete a recording for everyone (B6.3c). Anyone in the session. */
    public function deleteRecording(VideoSession $session, VideoRecording $recording, #[CurrentUser] User $user, SessionRecordings $recordings): Response
    {
        abort_unless($recording->video_session_id === $session->id, 404);
        $recordings->delete($recording, $user);

        return response()->noContent();
    }
}
