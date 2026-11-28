<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Learn;

use App\Content\Learning\Learning;
use App\Content\Player\AskInstructor;
use App\Content\Player\SeatTime;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\AskInstructorRequest;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The course player's seat time and check-ins (A8.2, ADR 0030), for the web
 * lesson page and the phone. The server decides what counts.
 */
final class PlayerController extends Controller
{
    /**
     * Send a heartbeat.
     *
     * Every 15 seconds while the lesson is open: where the video is, whether
     * it plays and whether the page or app is in front. With `mode: audio`
     * (A14.2) the phone is reading the lesson aloud and the screen may be
     * off; it counts only where the course allows audio-only study
     * (`audio_not_allowed` otherwise). Returns what has
     * counted, why the last stretch didn't (if it didn't), and any check-in
     * now waiting.
     *
     * @response array{data: array{counted: int, required: int, percent: int, reason: 'gap'|'paused'|'hidden'|'checkin_due'|'daily_max'|'audio_not_allowed'|null, checkin: array{key: string, at: int, kind: 'question'|'presence', question: array{id: int, prompt: string, options: list<string>}|null}|null, completed: bool, today_left: int|null}}
     */
    public function heartbeat(Request $request, int $lesson, #[CurrentUser] User $user, Learning $learning, SeatTime $seatTime): JsonResponse
    {
        $found = $this->open($lesson, $user, $learning);
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0', 'max:86400'],
            'playing' => ['required', 'boolean'],
            'visible' => ['required', 'boolean'],
            'session' => ['required', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'mode' => ['nullable', 'string', 'in:screen,audio'],
        ]);

        return response()->json(['data' => $seatTime->beat($user, $found, [
            'position' => (int) $data['position'], 'playing' => (bool) $data['playing'], 'visible' => (bool) $data['visible'],
            'session' => (string) $data['session'], 'timezone' => is_string($data['timezone'] ?? null) ? $data['timezone'] : 'America/Chicago',
            'source' => $request->is('api/*') ? 'app' : 'web',
            'mode' => ($data['mode'] ?? null) === 'audio' ? 'audio' : 'screen',
        ])]);
    }

    /**
     * Listen to the lesson.
     *
     * Audio-only (A14.2): the lesson's text by heading (or its video's words) for the phone to read aloud, how long that takes, whether audio-only study counts for this course (CE and qualifying courses only once the school has confirmed it), and the seat time so far. Heartbeats then go with `mode: audio`; check-ins are asked and answered out loud.
     *
     * @response array{data: array{id: int, title: string, course: string, language: 'en'|'es', sections: list<array{heading: string|null, text: string}>, words: int, seconds: int, audio_allowed: bool, player: array{counted: int, required: int, percent: int, reason: 'gap'|'paused'|'hidden'|'checkin_due'|'daily_max'|'audio_not_allowed'|null, checkin: array{key: string, at: int, kind: 'question'|'presence', question: array{id: int, prompt: string, options: list<string>}|null}|null, completed: bool, today_left: int|null}}}
     */
    public function listen(int $lesson, #[CurrentUser] User $user, Learning $learning, SeatTime $seatTime): JsonResponse
    {
        $found = $this->open($lesson, $user, $learning);

        return response()->json(['data' => [
            'id' => $found->id,
            'title' => $found->title,
            'course' => $found->course->title,
            'language' => $found->language === 'es' ? 'es' : 'en',
            ...$learning->listen($found, $user->locale->value),
            'audio_allowed' => $seatTime->audioAllowed($found->course),
            'player' => $seatTime->current($user, $found),
        ]]);
    }

    /**
     * Answer a check-in.
     *
     * A wrong answer still counts as present and returns why, with its source.
     *
     * @response array{correct: bool|null, explanation: string|null, source: string|null, state: array{counted: int, required: int, percent: int, reason: 'gap'|'paused'|'hidden'|'checkin_due'|'daily_max'|'audio_not_allowed'|null, checkin: array{key: string, at: int, kind: 'question'|'presence', question: array{id: int, prompt: string, options: list<string>}|null}|null, completed: bool, today_left: int|null}}
     */
    public function checkin(Request $request, int $lesson, #[CurrentUser] User $user, Learning $learning, SeatTime $seatTime): JsonResponse
    {
        $found = $this->open($lesson, $user, $learning);
        $data = $request->validate(['key' => ['required', 'string', 'max:40'], 'choice' => ['nullable', 'integer', 'min:0', 'max:5']]);

        return response()->json($seatTime->answer($user, $found, (string) $data['key'], is_numeric($data['choice'] ?? null) ? (int) $data['choice'] : null, $user->locale->value));
    }

    /**
     * Ask the instructor (Interrupt and ask).
     *
     * Mid-lesson: an answer from this course only, with where it comes from;
     * `simpler`, `spanish` or `quiz` build on the previous answer. Says so
     * when the course doesn't cover it. Free; 429 over the hourly AI limit.
     *
     * @response array{answer: string, sources: list<array{lesson_id: int, lesson: string, seconds: int|null, page: int|null, heading: string|null, text: string}>, quiz: array{prompt: string, options: list<string>, correct: int, explanation: string}|null, engine: 'ai'|'simulator'|'none', found: bool}
     */
    public function ask(AskInstructorRequest $request, int $lesson, #[CurrentUser] User $user, Learning $learning, AskInstructor $instructor): JsonResponse
    {
        $found = $this->open($lesson, $user, $learning);

        return response()->json($instructor->ask($user, $found, $request->question(), $request->mode(), $request->previous(), $learning->state($user)));
    }

    private function open(int $lesson, User $user, Learning $learning): Lesson
    {
        abort_unless($user->can('learning.study'), 403);
        $found = $learning->find($user, $lesson);
        abort_if($found === null || ! $learning->canOpen($user, $found), 404);

        return $found;
    }
}
