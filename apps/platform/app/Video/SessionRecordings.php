<?php

declare(strict_types=1);

namespace App\Video;

use App\Events\Video\SessionRecordingChanged;
use App\Messaging\Realtime;
use App\Models\User;
use App\Models\VideoRecording;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Audit\AuditLogger;
use App\Services\Content\LessonFiles;
use App\Services\Privacy\Consents;
use App\Video\Classes\ClassReports;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Throwable;

/**
 * Recording a session only with consent (B6.3c, ADR 0024). The host asks;
 * everyone in the room answers (each answer is a consent record for that
 * recording). It starts only when all of them agree; one "no" cancels it.
 * Anyone can stop it. Someone joining while it records must agree first.
 * LiveKit records to REOS's storage; only the people in the session can
 * play it, and it is deleted 90 days after the session.
 *
 * @phpstan-type RecordingData array{id: string, status: 'asking'|'declined'|'recording'|'stopping'|'ready'|'failed', requested_by: string|null, started_at: string|null, stopped_at: string|null, duration_seconds: int|null, expires_at: string|null, play_url: string|null, agreed: list<string>, waiting: list<string>, my_answer: bool|null}
 */
final readonly class SessionRecordings
{
    /** A link to play a recording lasts this long. */
    public const LINK_MINUTES = 60;

    public function __construct(
        private VideoSessions $sessions,
        private LiveKit $livekit,
        private Consents $consents,
        private AuditLogger $audit,
    ) {}

    /** The host asks to record; asking counts as their own agreement. */
    public function ask(VideoSession $session, User $host, ConsentMethod $method): VideoRecording
    {
        $participant = $this->sessions->ensureParticipant($session, $host);

        if ($participant->role !== VideoSessionParticipant::HOST) {
            throw ValidationException::withMessages(['recording' => __('sessions.errors.hostOnly')]);
        }

        if (! $session->open() || $this->current($session) !== null) {
            throw ValidationException::withMessages(['recording' => __('sessions.recording.errors.cannotAsk')]);
        }

        // A CE class records only when its approval allows it (A10.2c).
        if (! app(ClassReports::class)->mayRecord($session)) {
            throw ValidationException::withMessages(['recording' => __('classes.errors.noRecording')]);
        }

        $recording = $session->recordings()->create(['uuid' => (string) Str::uuid(), 'requested_by' => $host->id, 'status' => VideoRecording::ASKING]);
        $this->record($recording, $host, true, $method);
        $this->startIfAllAgreed($recording);
        $this->changed($recording);

        return $recording;
    }

    /** Someone in the room answers (or someone joining while it records). */
    public function answer(VideoRecording $recording, User $user, bool $agreed, ConsentMethod $method): void
    {
        $this->sessions->ensureParticipant($recording->session, $user);

        if (! in_array($recording->status, [VideoRecording::ASKING, VideoRecording::RECORDING], true)) {
            throw ValidationException::withMessages(['recording' => __('sessions.recording.errors.notAsking')]);
        }

        $this->record($recording, $user, $agreed, $method);

        if (! $agreed && $recording->status === VideoRecording::ASKING) {
            $recording->forceFill(['status' => VideoRecording::DECLINED])->save();
        } elseif ($agreed) {
            $this->startIfAllAgreed($recording);
        }

        $this->changed($recording);
    }

    /** Anyone in the session can stop it. */
    public function stop(VideoRecording $recording, ?User $by): void
    {
        if ($by !== null) {
            $this->sessions->ensureParticipant($recording->session, $by);
        }

        if ($recording->status === VideoRecording::ASKING) {
            $recording->forceFill(['status' => VideoRecording::DECLINED, 'stopped_by' => $by?->id])->save();
            $this->changed($recording);

            return;
        }

        if ($recording->status !== VideoRecording::RECORDING) {
            return;
        }

        $simulated = $recording->egress_id === null;
        $recording->forceFill([
            'status' => $simulated ? VideoRecording::READY : VideoRecording::STOPPING,
            'stopped_at' => now(),
            'stopped_by' => $by?->id,
            'expires_at' => $simulated ? $this->expiry($recording) : null,
        ])->save();

        if (! $simulated && $recording->egress_id !== null) {
            try {
                $this->livekit->stopRecording($recording->egress_id);
            } catch (Throwable $e) {
                // The room closing at the end stops it too; LiveKit's webhook finishes it.
                report($e);
            }
        }

        $this->changed($recording);
    }

    /** The session ended: whatever was asked or recording stops. */
    public function sessionEnded(VideoSession $session): void
    {
        foreach ($session->recordings()->whereIn('status', [VideoRecording::ASKING, VideoRecording::RECORDING])->get() as $recording) {
            $this->stop($recording, null);
        }
    }

    /**
     * LiveKit finished writing the file (`egress_ended` webhook).
     *
     * @param  array{filename?: string, size?: int|string, duration?: int|string}|null  $file
     */
    public function finished(string $egressId, bool $complete, ?array $file): void
    {
        $recording = VideoRecording::query()->where('egress_id', $egressId)->first();

        if ($recording === null || in_array($recording->status, [VideoRecording::READY, VideoRecording::DELETED], true)) {
            return;
        }

        if (! $complete || $file === null) {
            $recording->forceFill(['status' => VideoRecording::FAILED, 'stopped_at' => $recording->stopped_at ?? now()])->save();
            $this->changed($recording);

            return;
        }

        // LiveKit reports duration in nanoseconds.
        $duration = is_numeric($file['duration'] ?? null) ? (int) round(((float) $file['duration']) / 1_000_000_000) : null;

        $recording->forceFill([
            'status' => VideoRecording::READY,
            'path' => $this->path($recording),
            'size' => is_numeric($file['size'] ?? null) ? (int) $file['size'] : null,
            'duration_seconds' => $duration,
            'stopped_at' => $recording->stopped_at ?? now(),
            'expires_at' => $this->expiry($recording),
        ])->save();

        $this->changed($recording);
    }

    /** Anyone in the session can delete it for everyone. */
    public function delete(VideoRecording $recording, ?User $by): void
    {
        if ($by !== null) {
            $this->sessions->ensureParticipant($recording->session, $by);
        }

        if ($recording->status === VideoRecording::DELETED) {
            return;
        }

        if ($recording->path !== null) {
            LessonFiles::disk()->delete($recording->path);
        }

        $recording->forceFill(['status' => VideoRecording::DELETED, 'deleted_at' => now(), 'path' => null])->save();
        $this->audit->record('session.recording_deleted', 'video_session', $recording->session->uuid, ['by' => $by === null ? 'retention' : 'participant'], $by);
    }

    /** Deletes recordings past their 90 days (`reos:recordings:prune`, daily). */
    public function prune(): int
    {
        $due = VideoRecording::query()->whereIn('status', [VideoRecording::READY, VideoRecording::FAILED])->where('expires_at', '<=', now())->get();

        foreach ($due as $recording) {
            $this->delete($recording, null);
        }

        return $due->count();
    }

    /** The recording being asked for or made, if any. */
    public function current(VideoSession $session): ?VideoRecording
    {
        return $session->recordings()->whereIn('status', [VideoRecording::ASKING, VideoRecording::RECORDING, VideoRecording::STOPPING])->latest('id')->first();
    }

    /** Whether someone may enter the room now: while it records, only once they've agreed. */
    public function mayJoin(VideoSession $session, User $user): bool
    {
        $current = $this->current($session);

        // While it's only being asked, people come in and are asked too.
        return $current === null || $current->status !== VideoRecording::RECORDING
            || $current->consents()->where('user_id', $user->id)->where('agreed', true)->exists();
    }

    /** @return RecordingData */
    public function present(VideoRecording $recording, User $viewer): array
    {
        $answers = $recording->consents()->with('user')->get();
        $agreed = [];
        $mine = null;

        foreach ($answers as $answer) {
            if ($answer->agreed) {
                $agreed[] = $answer->user->name ?? '';
            }

            if ($answer->user_id === $viewer->id) {
                $mine = $answer->agreed;
            }
        }

        $answered = $answers->pluck('user_id')->all();
        $waiting = $recording->status === VideoRecording::ASKING
            ? array_values($recording->session->participants()->with('user')->where('in_room', true)->whereNotIn('user_id', $answered)->get()->map(static fn (VideoSessionParticipant $p): string => $p->user->name ?? '')->all())
            : [];

        /** @var RecordingData */
        return [
            'id' => $recording->uuid,
            'status' => $recording->status,
            'requested_by' => $recording->requester?->name,
            'started_at' => $recording->started_at?->toIso8601String(),
            'stopped_at' => $recording->stopped_at?->toIso8601String(),
            'duration_seconds' => $recording->duration_seconds,
            'expires_at' => $recording->expires_at?->toIso8601String(),
            'play_url' => $this->playUrl($recording),
            'agreed' => $agreed,
            'waiting' => $waiting,
            'my_answer' => $mine,
        ];
    }

    /** A link to play it, for the people in the session; local disk goes through a signed REOS route. */
    public function playUrl(VideoRecording $recording): ?string
    {
        if ($recording->status !== VideoRecording::READY || $recording->path === null) {
            return null;
        }

        $disk = LessonFiles::disk();

        if ($disk->getAdapter() instanceof LocalFilesystemAdapter) {
            return URL::temporarySignedRoute('sessions.recordings.file', now()->addMinutes(self::LINK_MINUTES), ['recording' => $recording->uuid]);
        }

        return $disk->temporaryUrl($recording->path, now()->addMinutes(self::LINK_MINUTES), ['ResponseContentType' => 'video/mp4']);
    }

    public function path(VideoRecording $recording): string
    {
        return 'recordings/'.$recording->session->uuid.'/'.$recording->uuid.'.mp4';
    }

    private function record(VideoRecording $recording, User $user, bool $agreed, ConsentMethod $method): void
    {
        DB::transaction(function () use ($recording, $user, $agreed, $method): void {
            $consent = $agreed
                ? $this->consents->grant($user, ConsentPurpose::SessionRecording, $method, $recording->consentSubject())
                : $this->consents->withdraw($user, ConsentPurpose::SessionRecording, $method, $recording->consentSubject());

            $recording->consents()->updateOrCreate(['user_id' => $user->id], ['agreed' => $agreed, 'consent_record_id' => $consent->id]);
        });
    }

    /** Everyone in the room said yes: LiveKit starts recording. */
    private function startIfAllAgreed(VideoRecording $recording): void
    {
        if ($recording->status !== VideoRecording::ASKING) {
            return;
        }

        $ids = static fn (iterable $values): array => array_map(static fn ($v): int => is_numeric($v) ? (int) $v : 0, [...$values]);
        $present = $ids($recording->session->participants()->where('in_room', true)->pluck('user_id'));
        $agreed = $ids($recording->consents()->where('agreed', true)->pluck('user_id'));

        if (array_diff($present, $agreed) !== []) {
            return;
        }

        $egress = $this->livekit->startRecording($recording->session, $this->path($recording));
        $recording->forceFill(['status' => VideoRecording::RECORDING, 'started_at' => now(), 'egress_id' => $egress])->save();
        $this->audit->record('session.recording_started', 'video_session', $recording->session->uuid, ['people' => count($agreed)], $recording->requester);
    }

    private function expiry(VideoRecording $recording): CarbonImmutable
    {
        return $recording->session->ends_at->addDays(VideoRecording::KEEP_DAYS);
    }

    private function changed(VideoRecording $recording): void
    {
        $ids = [];

        foreach ($recording->session->participants()->pluck('user_id') as $id) {
            $ids[] = is_numeric($id) ? (int) $id : 0;
        }

        Realtime::send(new SessionRecordingChanged($recording->session, $ids, $recording->status));
    }
}
