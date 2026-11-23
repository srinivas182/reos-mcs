<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Learn;

use App\Content\Learning\Learning;
use App\Content\Player\SeatTime;
use App\Http\Controllers\Controller;
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
     * it plays and whether the page or app is in front. Returns what has
     * counted, why the last stretch didn't (if it didn't), and any check-in
     * now waiting.
     *
     * @response array{data: array{counted: int, required: int, percent: int, reason: 'gap'|'paused'|'hidden'|'checkin_due'|'daily_max'|null, checkin: array{key: string, at: int, kind: 'question'|'presence', question: array{id: int, prompt: string, options: list<string>}|null}|null, completed: bool, today_left: int|null}}
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
        ]);

        return response()->json(['data' => $seatTime->beat($user, $found, [
            'position' => (int) $data['position'], 'playing' => (bool) $data['playing'], 'visible' => (bool) $data['visible'],
            'session' => (string) $data['session'], 'timezone' => is_string($data['timezone'] ?? null) ? $data['timezone'] : 'America/Chicago',
            'source' => $request->is('api/*') ? 'app' : 'web',
        ])]);
    }

    /**
     * Answer a check-in.
     *
     * A wrong answer still counts as present and returns why, with its source.
     *
     * @response array{correct: bool|null, explanation: string|null, source: string|null, state: array{counted: int, required: int, percent: int, reason: 'gap'|'paused'|'hidden'|'checkin_due'|'daily_max'|null, checkin: array{key: string, at: int, kind: 'question'|'presence', question: array{id: int, prompt: string, options: list<string>}|null}|null, completed: bool, today_left: int|null}}
     */
    public function checkin(Request $request, int $lesson, #[CurrentUser] User $user, Learning $learning, SeatTime $seatTime): JsonResponse
    {
        $found = $this->open($lesson, $user, $learning);
        $data = $request->validate(['key' => ['required', 'string', 'max:40'], 'choice' => ['nullable', 'integer', 'min:0', 'max:5']]);

        return response()->json($seatTime->answer($user, $found, (string) $data['key'], is_numeric($data['choice'] ?? null) ? (int) $data['choice'] : null, $user->locale->value));
    }

    private function open(int $lesson, User $user, Learning $learning): Lesson
    {
        abort_unless($user->can('learning.study'), 403);
        $found = $learning->find($user, $lesson);
        abort_if($found === null || ! $learning->canOpen($user, $found), 404);

        return $found;
    }
}
