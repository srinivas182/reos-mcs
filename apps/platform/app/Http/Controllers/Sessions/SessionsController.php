<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sessions;

use App\Enums\QuizTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sessions\ScheduleSessionRequest;
use App\Http\Requests\Sessions\SessionNotesRequest;
use App\Http\Requests\Sessions\ShareRequest;
use App\Http\Requests\Sessions\WhiteboardRequest;
use App\Messaging\Contacts;
use App\Models\User;
use App\Models\VideoSession;
use App\Services\Content\LessonFiles;
use App\Video\LiveKit;
use App\Video\SessionBoard;
use App\Video\SessionPresenter;
use App\Video\SessionShares;
use App\Video\VideoSessions;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /** The saved drawing, for someone joining late (B6.3b). */
    public function whiteboard(VideoSession $session, #[CurrentUser] User $user, SessionBoard $board): JsonResponse
    {
        return response()->json($board->load($session, $user));
    }

    /** Saves the drawing and its picture (B6.3b); the room saves as people draw. */
    public function saveWhiteboard(WhiteboardRequest $request, VideoSession $session, #[CurrentUser] User $user, SessionBoard $board): JsonResponse
    {
        $image = $request->file('image');
        $board->save($session, $user, $request->elements(), $image instanceof UploadedFile ? $image : null);
        $session->refresh();

        return response()->json(['image_url' => SessionBoard::imageUrl($session), 'updated_at' => $session->whiteboard_updated_at?->toIso8601String()]);
    }

    /** What the host can share: lessons and practice-question topics (B6.3b). */
    public function shareOptions(VideoSession $session, #[CurrentUser] User $user, SessionShares $shares): JsonResponse
    {
        return response()->json($shares->options($session, $user));
    }

    /** Share a lesson or send practice questions (B6.3b, host only). */
    public function share(ShareRequest $request, VideoSession $session, #[CurrentUser] User $user, SessionShares $shares): JsonResponse
    {
        $share = $request->string('kind')->toString() === 'lesson'
            ? $shares->shareLesson($session, $user, $request->integer('lesson'))
            : $shares->shareQuestions($session, $user, QuizTopic::from($request->string('topic')->toString()));

        return response()->json(SessionShares::present($share, app()->getLocale()), 201);
    }

    /** The picture of the board, on the local disk, through its signed link. */
    public function whiteboardImage(VideoSession $session): BinaryFileResponse
    {
        $disk = LessonFiles::disk();
        abort_unless($session->whiteboard_image !== null && $disk->exists($session->whiteboard_image), 404);

        return response()->file($disk->path($session->whiteboard_image), [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
