<?php

declare(strict_types=1);

namespace App\Content\Player;

use App\Content\Learning\Learning;
use App\Content\Video\VideoHosts;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\OfflineGrant;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Content\LessonFiles;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lessons saved on the phone (A8.2b, ADR 0030, decision 1: 720p video).
 *
 * Saving a lesson makes a grant for that learner, lesson and phone (the
 * phone's signed-in device): a link to the 720p download (Mux's static
 * rendition, or the original file without Mux) valid an hour, everything
 * the player needs offline, and a key to sign the offline seat time with.
 * A grant lasts 30 days; the phone renews it whenever it is online, which
 * works only while the learner still has the course. When access ends the
 * grant is revoked and the phone deletes the file.
 *
 * @phpstan-import-type State from SeatTime
 */
final readonly class OfflineDownloads
{
    /** Days a download works between online checks. */
    public const DAYS = 30;

    /** About 180 MB an hour at 720p, when the host hasn't said the size. */
    public const BYTES_PER_SECOND = 50_000;

    public function __construct(private Learning $learning, private SeatTime $seatTime, private VideoHosts $videos) {}

    /**
     * Save a lesson on this phone.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException when the lesson has no video to save, or its download isn't ready yet
     */
    public function grant(User $user, Lesson $lesson, string $device): array
    {
        $video = $this->learning->videoFor($lesson, $user->locale->value);
        $url = $video === null ? null : $this->downloadUrl($video);

        if ($video === null || $url === null) {
            throw ValidationException::withMessages(['lesson' => __($video === null ? 'player.offline.noVideo' : 'player.offline.notReady')]);
        }

        $bytes = $video->offline['bytes'] ?? ($video->mux_playback_id === null ? $video->size : null) ?? (int) (($video->duration_seconds ?? ($lesson->minutes ?? 10) * 60) * self::BYTES_PER_SECOND);
        $grant = OfflineGrant::query()->updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id, 'device' => $device],
            ['key' => Str::random(64), 'bytes' => $bytes, 'expires_at' => now()->addDays(self::DAYS), 'revoked_at' => null],
        );
        $state = $this->seatTime->current($user, $lesson);

        return [
            'grant' => $grant->id,
            'key' => $grant->key,
            'url' => $url,
            'bytes' => $bytes,
            'expires_at' => $grant->expires_at->toIso8601String(),
            'lesson' => $this->learning->lesson($lesson, $user->locale->value),
            'player' => $state,
            'answered' => $this->seatTime->answered($user, $lesson),
            // With answers, so a check-in can show why offline. Check-ins measure presence: a wrong answer still counts.
            'checkpoints' => array_map(fn (array $p): array => [...$p, 'answer' => $this->answerFor($p, $user->locale->value)], $this->seatTime->checkpoints($lesson, $state['required'], $user->locale->value)),
            'rules' => $this->seatTime->rulesFor($lesson->course),
        ];
    }

    /**
     * The phone's saved lessons and whether each still works: `active` (and
     * good for another 30 days), or `revoked` (access ended, or removed) for
     * the phone to delete.
     *
     * @return list<array{grant: int, lesson_id: int, title: string, course: string, bytes: int|null, expires_at: string, status: 'active'|'revoked'}>
     */
    public function list(User $user, string $device): array
    {
        $list = [];

        foreach (OfflineGrant::query()->where('user_id', $user->id)->where('device', $device)->with('lesson.course')->orderBy('id')->get() as $g) {
            // Listing online is the 30-day check: still has the course → another 30 days.
            $usable = $this->renew($user, $g) !== null;
            $list[] = [
                'grant' => $g->id, 'lesson_id' => $g->lesson_id, 'title' => $g->lesson->title, 'course' => $g->lesson->course->title,
                'bytes' => $g->bytes, 'expires_at' => $g->expires_at->toIso8601String(), 'status' => $usable ? 'active' : 'revoked',
            ];
        }

        return $list;
    }

    /** Another 30 days, while the learner still has the course; null once it has ended (the phone deletes the file). */
    public function renew(User $user, OfflineGrant $grant): ?CarbonImmutable
    {
        if (! $this->check($user, $grant) || $grant->revoked_at !== null) {
            return null;
        }

        $grant->forceFill(['expires_at' => now()->addDays(self::DAYS)])->save();

        return CarbonImmutable::parse($grant->expires_at);
    }

    public function remove(OfflineGrant $grant): void
    {
        $grant->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * The phone's offline seat time, signed with the grant's key over the
     * exact JSON it sends. Accepted even after the grant expired (the time
     * was spent while it was valid), but never when the signature is wrong.
     *
     * @return array{accepted: int, duplicates: int, rejected: int, state: State}
     *
     * @throws ValidationException when the signature or the payload is wrong
     */
    public function replay(User $user, OfflineGrant $grant, string $payload, string $signature, string $timezone): array
    {
        if (! hash_equals(hash_hmac('sha256', $payload, $grant->key), strtolower($signature))) {
            throw ValidationException::withMessages(['signature' => __('player.offline.badSignature')]);
        }

        $decoded = json_decode($payload, true);
        $events = [];

        foreach (is_array($decoded) ? $decoded : [] as $event) {
            if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['at'] ?? null) || ! in_array($event['type'] ?? null, ['watch', 'checkin'], true)) {
                throw ValidationException::withMessages(['payload' => __('player.offline.badPayload')]);
            }

            $events[] = [
                'id' => $event['id'], 'type' => $event['type'], 'at' => $event['at'],
                'seconds' => is_int($event['seconds'] ?? null) ? $event['seconds'] : 0,
                'key' => is_string($event['key'] ?? null) ? $event['key'] : '',
                'choice' => is_int($event['choice'] ?? null) ? $event['choice'] : null,
            ];
        }

        if (count($events) > 500) {
            throw ValidationException::withMessages(['payload' => __('player.offline.badPayload')]);
        }

        return $this->seatTime->replay($user, $grant, $events, $timezone);
    }

    /** Revokes the grant once the learner can no longer open the lesson. */
    private function check(User $user, OfflineGrant $grant): bool
    {
        if ($grant->revoked_at !== null) {
            return false;
        }

        $lesson = $this->learning->find($user, $grant->lesson_id);

        if ($lesson === null || ! $this->learning->canOpen($user, $lesson)) {
            $grant->forceFill(['revoked_at' => now()])->save();

            return false;
        }

        return true;
    }

    private function downloadUrl(LessonMedia $video): ?string
    {
        if ($video->mux_playback_id !== null) {
            $mux = $this->videos->mux();

            return $mux !== null && $video->offline !== null ? $mux->downloadUrl($video->mux_playback_id) : null;
        }

        return LessonFiles::playableUrl($video, 60);
    }

    /**
     * @param  array{kind: string, question: array{id: int}|null}  $point
     * @return array{correct: int, explanation: string, source: string|null}|null
     */
    private function answerFor(array $point, string $language): ?array
    {
        if ($point['question'] === null) {
            return null;
        }

        $question = QuizQuestion::query()->find($point['question']['id']);

        return $question === null ? null : ['correct' => $question->correct_option, 'explanation' => $question->in($language)['explanation'], 'source' => $question->source];
    }
}
