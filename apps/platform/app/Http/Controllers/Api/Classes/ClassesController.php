<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Classes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\ClassPollRequest;
use App\Http\Requests\Classes\ScheduleClassRequest;
use App\Models\ClassCheck;
use App\Models\ClassPoll;
use App\Models\ClassQuestion;
use App\Models\User;
use App\Models\VideoSession;
use App\Video\Classes\ClassReports;
use App\Video\Classes\ClassRoom;
use App\Video\Classes\LiveClasses;
use App\Video\VideoSessions;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live classes (A10.2b, ADR 0040; candidate L12, instructor i11 and i12):
 * scheduling, registration, and the class tools in the room: polls,
 * raised hands, questions with likes, chat and attendance checks.
 */
final class ClassesController extends Controller
{
    /**
     * Live classes.
     *
     * Instructors and mentors: their classes and the courses they can schedule for. Students: upcoming classes for their courses (optionally one course), then ones they registered for in the last 30 days.
     *
     * @response array{data: list<array{id: string, title: string, agenda: string|null, course: array{id: int, title: string}, host: string, starts_at: string, ends_at: string, minutes: int, status: 'scheduled'|'live'|'ended'|'cancelled', ce: bool, ce_hours: float|null, checkin_minutes: int|null, registered: int, capacity: int, me: 'host'|'registered'|null, can_register: bool, full: bool, can_join: bool, report: 'none'|'draft'|'sent', has_recording: bool}>, can_schedule: bool, courses: list<array{id: int, title: string, ce: bool}>, lengths: list<int>}
     */
    public function index(Request $request, #[CurrentUser] User $user, LiveClasses $classes, VideoSessions $sessions): JsonResponse
    {
        // Students: only one course's classes (the course's Live tab).
        $request->validate(['course' => ['nullable', 'integer']]);
        $host = $sessions->canHost($user);

        return response()->json([
            'data' => $host ? $classes->forHost($user) : $classes->forStudent($user, $request->filled('course') ? $request->integer('course') : null),
            'can_schedule' => $host,
            'courses' => $host ? $classes->courses($user) : [],
            'lengths' => LiveClasses::LENGTHS,
        ]);
    }

    /**
     * Schedule a live class.
     *
     * A course with a live delivery approval in force makes it a CE class, with the approval's attendance checks.
     *
     * @status 201
     *
     * @response array{data: array{id: string, title: string, agenda: string|null, course: array{id: int, title: string}, host: string, starts_at: string, ends_at: string, minutes: int, status: 'scheduled'|'live'|'ended'|'cancelled', ce: bool, ce_hours: float|null, checkin_minutes: int|null, registered: int, capacity: int, me: 'host'|'registered'|null, can_register: bool, full: bool, can_join: bool, report: 'none'|'draft'|'sent', has_recording: bool}}
     */
    public function store(ScheduleClassRequest $request, #[CurrentUser] User $user, LiveClasses $classes): JsonResponse
    {
        $session = $classes->schedule($user, $request->plan());

        return response()->json(['data' => $classes->row($session->load(['host', 'liveClass.course', 'liveClass.approval']), $user)], 201);
    }

    /**
     * Register for a class.
     *
     * Up to its capacity; reminders come an hour and 10 minutes before.
     *
     * @response array{data: array{id: string, title: string, agenda: string|null, course: array{id: int, title: string}, host: string, starts_at: string, ends_at: string, minutes: int, status: 'scheduled'|'live'|'ended'|'cancelled', ce: bool, ce_hours: float|null, checkin_minutes: int|null, registered: int, capacity: int, me: 'host'|'registered'|null, can_register: bool, full: bool, can_join: bool, report: 'none'|'draft'|'sent', has_recording: bool}}
     */
    public function register(VideoSession $session, #[CurrentUser] User $user, LiveClasses $classes): JsonResponse
    {
        $classes->register($user, $session);

        return response()->json(['data' => $classes->row($session->refresh(), $user)]);
    }

    /**
     * Cancel my registration.
     *
     * Until the class starts.
     *
     * @response array{data: array{id: string, title: string, agenda: string|null, course: array{id: int, title: string}, host: string, starts_at: string, ends_at: string, minutes: int, status: 'scheduled'|'live'|'ended'|'cancelled', ce: bool, ce_hours: float|null, checkin_minutes: int|null, registered: int, capacity: int, me: 'host'|'registered'|null, can_register: bool, full: bool, can_join: bool, report: 'none'|'draft'|'sent', has_recording: bool}}
     */
    public function unregister(VideoSession $session, #[CurrentUser] User $user, LiveClasses $classes): JsonResponse
    {
        $classes->unregister($user, $session);

        return response()->json(['data' => $classes->row($session->refresh(), $user)]);
    }

    /**
     * The class right now.
     *
     * Viewers, polls, questions (most liked first), chat and the open attendance check; for the host also raised hands and every check's answers. Fetch again on `class.changed`.
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function live(VideoSession $session, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Raise or lower my hand.
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function hand(Request $request, VideoSession $session, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        $request->validate(['raised' => ['required', 'boolean']]);
        $room->raiseHand($session, $user, $request->boolean('raised'));

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Bring a student on, or take them off.
     *
     * On stage they may speak and show video (host only).
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function stage(Request $request, VideoSession $session, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        $request->validate(['user' => ['required', 'integer'], 'on' => ['required', 'boolean']]);
        $room->stage($session, $user, $request->integer('user'), $request->boolean('on'));

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Launch a poll.
     *
     * Closes the one before (host only).
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function poll(ClassPollRequest $request, VideoSession $session, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        $room->poll($session, $user, $request->string('question')->trim()->toString(), $request->options());

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Close a poll.
     *
     * Everyone sees the results.
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function closePoll(VideoSession $session, ClassPoll $poll, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        $room->closePoll($session, $user, $poll);

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Answer a poll.
     *
     * Once; the results appear.
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function vote(Request $request, VideoSession $session, ClassPoll $poll, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        // The answer's position, from 0.
        $request->validate(['option' => ['required', 'integer', 'min:0', 'max:4']]);
        $room->vote($session, $user, $poll, $request->integer('option'));

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Ask the instructor.
     *
     * The question joins the instructor's queue; classmates' likes move it up.
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function ask(Request $request, VideoSession $session, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'min:3', 'max:500']]);
        $room->ask($session, $user, $request->string('body')->trim()->toString());

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Like a question, or take the like back.
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function like(Request $request, VideoSession $session, ClassQuestion $question, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        $request->validate(['like' => ['required', 'boolean']]);
        $room->like($session, $user, $question, $request->boolean('like'));

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Mark a question answered.
     *
     * Host only.
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function answered(Request $request, VideoSession $session, ClassQuestion $question, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        $request->validate(['answered' => ['required', 'boolean']]);
        $room->answered($session, $user, $question, $request->boolean('answered'));

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Say something in the chat.
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function chat(Request $request, VideoSession $session, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'min:1', 'max:500']]);
        $room->chat($session, $user, $request->string('body')->trim()->toString());

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Send an attendance check now.
     *
     * Host only; open for three minutes. CE classes also send them on the approval's interval.
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function check(VideoSession $session, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        $room->sendCheck($session, $user);

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * Answer an attendance check.
     *
     * "I'm here", while it's open.
     *
     * @response array{data: array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}}
     */
    public function answerCheck(VideoSession $session, ClassCheck $check, #[CurrentUser] User $user, ClassRoom $room): JsonResponse
    {
        $room->answerCheck($session, $user, $check);

        return response()->json(['data' => $room->live($session, $user)]);
    }

    /**
     * The CE class report.
     *
     * After a CE class: who joined, earned CE, didn't, and borderline cases to review, by the approval's rules. For the instructor, and the school's admins.
     *
     * @response array{data: array{class: array{id: string, title: string, course: string, starts_at: string, ends_at: string, minutes: int, approval: string|null, ce_hours: float|null}, rules: array{presence_percent: int, required_minutes: int, checkin_minutes: int, checks: int}, status: 'none'|'draft'|'sent', sent_at: string|null, sent_by: string|null, can_decide: bool, can_send: bool, counts: array{joined: int, earned: int, not_earned: int, review: int, undecided: int}, students: list<array{user_id: int, name: string, minutes_present: int, checks_answered: int, checks_total: int, outcome: 'earned'|'review'|'not_earned', reasons: list<string>, decision: 'accepted'|'rejected'|null, decision_reason: string|null, earned: bool}>}}
     */
    public function report(VideoSession $session, #[CurrentUser] User $user, ClassReports $reports): JsonResponse
    {
        return response()->json(['data' => $reports->report($session, $user)]);
    }

    /**
     * Accept or reject a borderline case.
     *
     * With a reason; every decision is logged.
     *
     * @response array{data: array{class: array{id: string, title: string, course: string, starts_at: string, ends_at: string, minutes: int, approval: string|null, ce_hours: float|null}, rules: array{presence_percent: int, required_minutes: int, checkin_minutes: int, checks: int}, status: 'none'|'draft'|'sent', sent_at: string|null, sent_by: string|null, can_decide: bool, can_send: bool, counts: array{joined: int, earned: int, not_earned: int, review: int, undecided: int}, students: list<array{user_id: int, name: string, minutes_present: int, checks_answered: int, checks_total: int, outcome: 'earned'|'review'|'not_earned', reasons: list<string>, decision: 'accepted'|'rejected'|null, decision_reason: string|null, earned: bool}>}}
     */
    public function decide(Request $request, VideoSession $session, #[CurrentUser] User $user, ClassReports $reports): JsonResponse
    {
        // The reason is kept with the report.
        $request->validate([
            'user' => ['required', 'integer'],
            'decision' => ['required', 'in:accepted,rejected'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $reports->decide($session, $user, $request->integer('user'), $request->string('decision')->toString() === 'accepted', $request->string('reason')->trim()->toString());

        return response()->json(['data' => $reports->report($session, $user)]);
    }

    /**
     * Confirm and send to school.
     *
     * Once every borderline case is decided; the school's admins are told.
     *
     * @response array{data: array{class: array{id: string, title: string, course: string, starts_at: string, ends_at: string, minutes: int, approval: string|null, ce_hours: float|null}, rules: array{presence_percent: int, required_minutes: int, checkin_minutes: int, checks: int}, status: 'none'|'draft'|'sent', sent_at: string|null, sent_by: string|null, can_decide: bool, can_send: bool, counts: array{joined: int, earned: int, not_earned: int, review: int, undecided: int}, students: list<array{user_id: int, name: string, minutes_present: int, checks_answered: int, checks_total: int, outcome: 'earned'|'review'|'not_earned', reasons: list<string>, decision: 'accepted'|'rejected'|null, decision_reason: string|null, earned: bool}>}}
     */
    public function sendReport(VideoSession $session, #[CurrentUser] User $user, ClassReports $reports): JsonResponse
    {
        $reports->send($session, $user);

        return response()->json(['data' => $reports->report($session, $user)]);
    }
}
