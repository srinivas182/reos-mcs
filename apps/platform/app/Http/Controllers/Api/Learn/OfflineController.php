<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Learn;

use App\Content\Learning\Learning;
use App\Content\Player\OfflineDownloads;
use App\Http\Controllers\Controller;
use App\Models\OfflineGrant;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lessons saved on the phone (A8.2b, ADR 0030; Candidate App Journey screen
 * 34, Downloads). A download belongs to the phone that saved it: the
 * signed-in device of the request.
 */
final class OfflineController extends Controller
{
    /**
     * My saved lessons on this phone.
     *
     * Each is `active`, or `revoked` (access ended, or removed elsewhere): the
     * phone deletes those files.
     *
     * @response array{data: list<array{grant: int, lesson_id: int, title: string, course: string, bytes: int|null, expires_at: string, status: 'active'|'revoked'}>}
     */
    public function index(Request $request, #[CurrentUser] User $user, OfflineDownloads $downloads): JsonResponse
    {
        return response()->json(['data' => $downloads->list($user, $this->device($request, $user))]);
    }

    /**
     * Save a lesson on this phone.
     *
     * Returns a link to the 720p download (valid an hour), the lesson as the
     * player needs it offline, its check-ins with answers, the seat-time
     * rules, and the key to sign offline seat time with (HMAC-SHA256). Works
     * for 30 days between online checks. 422 when the lesson has no video or
     * its download isn't ready yet.
     *
     * @status 201
     *
     * @response array{data: array{grant: int, key: string, url: string, bytes: int|null, expires_at: string, lesson: array{id: int, title: string, language: 'en'|'es', minutes: int|null, course: array{id: int, title: string}, sections: list<array{heading: string|null, text: string}>, documents: list<array{id: int, kind: 'slides'|'document', name: string, url: string, pages: list<array{page: int, text: string}>}>, video: array{type: 'hls'|'file', url: string, duration: int|null, poster: string|null}|null, transcript: list<array{seconds: int, text: string}>, captions: array{en?: list<array{seconds: int, text: string}>, es?: list<array{seconds: int, text: string}>}|null, previous: int|null, next: int|null}, player: array{counted: int, required: int, percent: int, reason: 'gap'|'paused'|'hidden'|'checkin_due'|'daily_max'|null, checkin: array{key: string, at: int, kind: 'question'|'presence', question: array{id: int, prompt: string, options: list<string>}|null}|null, completed: bool, today_left: int|null}, answered: list<string>, checkpoints: list<array{key: string, at: int, kind: 'question'|'presence', question: array{id: int, prompt: string, options: list<string>}|null, answer: array{correct: int, explanation: string, source: string|null}|null}>, rules: array{checkin_minutes: int, presence_percent: int, max_seconds_per_day: int|null}}}
     */
    public function store(Request $request, int $lesson, #[CurrentUser] User $user, Learning $learning, OfflineDownloads $downloads): JsonResponse
    {
        abort_unless($user->can('learning.study'), 403);
        $found = $learning->find($user, $lesson);
        abort_if($found === null || ! $learning->canOpen($user, $found), 404);

        return response()->json(['data' => $downloads->grant($user, $found, $this->device($request, $user))], 201);
    }

    /**
     * Check a saved lesson online.
     *
     * Another 30 days while I still have the course; 410 once access has
     * ended (delete the file).
     *
     * @response array{expires_at: string}
     */
    public function renew(Request $request, int $grant, #[CurrentUser] User $user, OfflineDownloads $downloads): JsonResponse
    {
        $expires = $downloads->renew($user, $this->grant($request, $user, $grant));

        return $expires === null
            ? response()->json(['message' => __('player.offline.ended')], 410)
            : response()->json(['expires_at' => $expires->toIso8601String()]);
    }

    /** Remove a saved lesson from this phone. */
    public function destroy(Request $request, int $grant, #[CurrentUser] User $user, OfflineDownloads $downloads): JsonResponse
    {
        $downloads->remove($this->grant($request, $user, $grant));

        return response()->json(null, 204);
    }

    /**
     * Send seat time recorded offline.
     *
     * `payload` is the JSON list of events exactly as signed:
     * `{"id", "type": "watch", "at", "seconds"}` or
     * `{"id", "type": "checkin", "at", "key", "choice"}`; `signature` is its
     * HMAC-SHA256 (hex) with the grant's key. Events may arrive in any order
     * and more than once; each counts once, under the same rules as online.
     *
     * @response array{accepted: int, duplicates: int, rejected: int, state: array{counted: int, required: int, percent: int, reason: 'gap'|'paused'|'hidden'|'checkin_due'|'daily_max'|null, checkin: array{key: string, at: int, kind: 'question'|'presence', question: array{id: int, prompt: string, options: list<string>}|null}|null, completed: bool, today_left: int|null}}
     */
    public function seatTime(Request $request, int $grant, #[CurrentUser] User $user, OfflineDownloads $downloads): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'string', 'max:200000'],
            'signature' => ['required', 'string', 'size:64'],
            'timezone' => ['nullable', 'string', 'max:60'],
        ]);

        return response()->json($downloads->replay(
            $user, $this->grant($request, $user, $grant), (string) $data['payload'], (string) $data['signature'],
            is_string($data['timezone'] ?? null) ? $data['timezone'] : 'America/Chicago',
        ));
    }

    private function grant(Request $request, User $user, int $id): OfflineGrant
    {
        return OfflineGrant::query()->where('user_id', $user->id)->where('device', $this->device($request, $user))->findOrFail($id);
    }

    /** The phone: the signed-in device of this request (403 without one). */
    private function device(Request $request, User $user): string
    {
        $token = $user->currentAccessToken();
        $device = $token instanceof PersonalAccessToken ? $token->device_id : null;
        abort_if($device === null || $device === '', 403, __('player.offline.phoneOnly'));

        return $device;
    }
}
