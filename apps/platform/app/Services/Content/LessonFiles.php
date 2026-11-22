<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Content\Extraction\Captions;
use App\Content\Extraction\Section;
use App\Content\Extraction\TextExtractor;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Content\Scanning\FileScanner;
use App\Content\Scanning\ScanResult;
use App\Content\Video\VideoHosts;
use App\Jobs\IngestLesson;
use App\Jobs\ProcessLessonMedia;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Lesson files (A5.3, ADR 0020): videos, slides, documents and captions.
 *
 * 1. `begin`: a short-lived signed link the browser uploads to directly
 *    (S3 in production), so files never pass through the app server.
 * 2. `complete`: the size and the file's first bytes must match what was
 *    declared; then it's queued.
 * 3. `process` (queued): virus scan, then video to the video host, words
 *    read from slides and documents, captions read; then the lesson's
 *    passages are rebuilt.
 */
final readonly class LessonFiles
{
    public function __construct(
        private AuditLogger $audit,
        private FileScanner $scanner,
        private VideoHosts $videos,
        private TextExtractor $extractor,
    ) {}

    public static function disk(): FilesystemAdapter
    {
        return Storage::disk(config()->string('reos.content.disk'));
    }

    /** @return array{media: LessonMedia, upload: array{method: string, url: string, headers: array<string, string>}} */
    public function begin(Lesson $lesson, MediaKind $kind, string $name, int $size, User $actor): array
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $kind->types()[$extension] ?? null;

        if ($mime === null) {
            throw ValidationException::withMessages(['name' => __('content.files.errors.type', ['types' => implode(', ', array_keys($kind->types()))])]);
        }

        if ($size < 1 || $size > $kind->maxBytes()) {
            throw ValidationException::withMessages(['size' => __('content.files.errors.size', ['size' => (int) round($kind->maxBytes() / 1024 / 1024).' MB'])]);
        }

        // REOS content lives under platform/; school content (Sprint 7) under tenants/<id>/.
        $path = 'platform/lessons/'.$lesson->id.'/'.Str::uuid7()->toString().'.'.$extension;

        $media = LessonMedia::query()->create([
            'lesson_id' => $lesson->id,
            'kind' => $kind,
            'path' => $path,
            'original_name' => Str::limit(basename($name), 190, ''),
            'mime' => $mime,
            'size' => $size,
            'status' => MediaStatus::AwaitingUpload,
        ]);

        $signed = self::disk()->temporaryUploadUrl($path, now()->addMinutes(config()->integer('reos.content.upload_minutes')), ['ContentType' => $mime]);
        $headers = ['Content-Type' => $mime];

        foreach ((array) ($signed['headers'] ?? []) as $header => $value) {
            if (is_string($header) && strtolower($header) !== 'host') {
                $headers[$header] = is_array($value) ? implode(', ', array_filter($value, is_string(...))) : (is_scalar($value) ? (string) $value : '');
            }
        }

        $this->audit->record('lesson.file_started', Lessons::AUDIT_SUBJECT, (string) $lesson->id, ['kind' => $kind->value, 'name' => $media->original_name], $actor);

        return ['media' => $media, 'upload' => ['method' => 'PUT', 'url' => (string) $signed['url'], 'headers' => $headers]];
    }

    /** The browser says the upload finished: check it, then queue the scan and processing. */
    public function complete(LessonMedia $media, User $actor): LessonMedia
    {
        if ($media->status !== MediaStatus::AwaitingUpload) {
            throw ValidationException::withMessages(['file' => __('content.files.errors.done')]);
        }

        $disk = self::disk();

        if (! $disk->exists($media->path)) {
            throw ValidationException::withMessages(['file' => __('content.files.errors.missing')]);
        }

        $reason = match (true) {
            $disk->size($media->path) !== $media->size => 'content.files.failed.size',
            ! MediaKind::looksLike($media->extension(), self::head($disk, $media->path)) => 'content.files.failed.type',
            default => null,
        };

        $this->audit->record('lesson.file_added', Lessons::AUDIT_SUBJECT, (string) $media->lesson_id, ['kind' => $media->kind->value, 'name' => $media->original_name, 'refused' => $reason], $actor);

        if ($reason !== null) {
            $disk->delete($media->path);
            $media->forceFill(['status' => MediaStatus::Rejected, 'failure' => $reason])->save();

            return $media;
        }

        $media->forceFill(['status' => MediaStatus::Processing, 'failure' => null])->save();
        ProcessLessonMedia::dispatch($media->id);

        return $media;
    }

    /** @param  (Closure(int): void)|null  $retry  try again after so many seconds (null: no more tries) */
    public function process(LessonMedia $media, ?Closure $retry = null): void
    {
        if ($media->status !== MediaStatus::Processing) {
            return;
        }

        $disk = self::disk();

        if ($media->scanned_at === null) {
            $stream = $disk->readStream($media->path);

            if (! is_resource($stream)) {
                $this->fail($media, 'content.files.failed.missing');

                return;
            }

            try {
                $result = $this->scanner->scan($stream);
            } finally {
                fclose($stream);
            }

            if ($result === ScanResult::Infected) {
                $disk->delete($media->path);
                $this->fail($media, 'content.files.failed.infected', MediaStatus::Rejected);

                return;
            }

            if ($result === ScanResult::Unavailable) {
                $retry === null ? $this->fail($media, 'content.files.failed.scanner') : $retry(300);

                return;
            }

            $media->forceFill(['scanned_at' => now()])->save();
        }

        try {
            match ($media->kind) {
                MediaKind::Video => $this->video($media, $disk),
                MediaKind::Slides, MediaKind::Document => $this->read($media, $disk),
                MediaKind::Captions => $this->captions($media, $disk),
            };
        } catch (Throwable $e) {
            report($e);
            $this->fail($media, $media->kind === MediaKind::Video ? 'content.files.failed.video' : 'content.files.failed.unreadable');

            return;
        }

        IngestLesson::dispatch($media->lesson_id);
    }

    public function failed(int $mediaId, string $reason): void
    {
        LessonMedia::query()->whereKey($mediaId)->where('status', MediaStatus::Processing)
            ->update(['status' => MediaStatus::Failed->value, 'failure' => $reason]);
    }

    /** Deletes the file (from storage and the video host) and rebuilds the lesson's passages. */
    public function remove(LessonMedia $media, User $actor): void
    {
        try {
            $this->videos->mux()?->remove($media);
        } catch (Throwable $e) {
            report($e);
        }

        self::disk()->delete($media->path);
        $media->delete();
        $this->audit->record('lesson.file_removed', Lessons::AUDIT_SUBJECT, (string) $media->lesson_id, ['kind' => $media->kind->value, 'name' => $media->original_name], $actor);
        IngestLesson::dispatch($media->lesson_id);
    }

    /** Video goes to the video host; with none (Off) the original file plays. */
    private function video(LessonMedia $media, FilesystemAdapter $disk): void
    {
        $host = $this->videos->host();

        if ($host === null) {
            $media->forceFill(['status' => MediaStatus::Ready])->save();

            return;
        }

        $host->start($media, $disk->temporaryUrl($media->path, now()->addHours(6)), $media->lesson->language);
    }

    private function read(LessonMedia $media, FilesystemAdapter $disk): void
    {
        $local = tempnam(sys_get_temp_dir(), 'reos-content-');

        if ($local === false) {
            throw new RuntimeException('No temporary file.');
        }

        try {
            $in = $disk->readStream($media->path);
            $out = fopen($local, 'wb');

            if (! is_resource($in) || $out === false) {
                throw new RuntimeException('The file could not be copied for reading.');
            }

            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);

            $sections = $this->extractor->extract($local, $media->extension());
        } finally {
            @unlink($local);
        }

        $media->forceFill([
            'sections' => array_map(static fn (Section $s): array => ['page' => $s->page, 'text' => $s->text], $sections),
            'pages' => count(array_filter($sections, static fn (Section $s): bool => $s->page !== null)) ?: null,
            'status' => MediaStatus::Ready,
        ])->save();
    }

    private function captions(LessonMedia $media, Filesystem $disk): void
    {
        $vtt = (string) $disk->get($media->path);

        if (Captions::cues($vtt) === []) {
            $this->fail($media, 'content.files.failed.noCaptions', MediaStatus::Rejected);

            return;
        }

        $media->forceFill(['transcript' => $vtt, 'status' => MediaStatus::Ready])->save();
    }

    private function fail(LessonMedia $media, string $reason, MediaStatus $status = MediaStatus::Failed): void
    {
        $media->forceFill(['status' => $status, 'failure' => $reason])->save();
    }

    private static function head(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            return '';
        }

        $head = (string) fread($stream, 16);
        fclose($stream);

        return $head;
    }
}
