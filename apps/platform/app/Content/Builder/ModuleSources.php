<?php

declare(strict_types=1);

namespace App\Content\Builder;

use App\Content\Extraction\Captions;
use App\Content\Extraction\Section;
use App\Content\Extraction\TextExtractor;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Content\Scanning\FileScanner;
use App\Content\Scanning\ScanResult;
use App\Jobs\ProcessModuleSource;
use App\Models\CourseModule;
use App\Models\ModuleSource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\LessonFiles;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * A module's source material (A7.2, ADR 0026; screen 43): the same safe
 * path as lesson files (signed upload straight to storage, size and first
 * bytes checked, virus scan) and then read: scripts and forms by heading
 * and page, slides page by page, captions line by line. Recorded classes are
 * kept as they are; their captions file gives their words. Kept under the
 * school's own folder, never shown to learners.
 */
final readonly class ModuleSources
{
    private const AUDIT_SUBJECT = 'course_module';

    public function __construct(
        private AuditLogger $audit,
        private FileScanner $scanner,
        private TextExtractor $extractor,
    ) {}

    /** @return array{source: ModuleSource, upload: array{method: string, url: string, headers: array<string, string>}} */
    public function begin(CourseModule $module, SourceRole $role, string $name, int $size, User $actor): array
    {
        $kind = $role->kind();
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $kind->types()[$extension] ?? null;

        if ($mime === null) {
            throw ValidationException::withMessages(['name' => __('content.files.errors.type', ['types' => implode(', ', array_keys($kind->types()))])]);
        }

        if ($size < 1 || $size > $kind->maxBytes()) {
            throw ValidationException::withMessages(['size' => __('content.files.errors.size', ['size' => (int) round($kind->maxBytes() / 1024 / 1024).' MB'])]);
        }

        $owner = $module->course->organisation_id;
        $path = ($owner === null ? 'platform' : 'tenants/'.$owner).'/courses/'.$module->course_id.'/sources/'.Str::uuid7()->toString().'.'.$extension;

        $source = $module->sources()->create([
            'role' => $role, 'kind' => $kind, 'path' => $path, 'original_name' => Str::limit(basename($name), 190, ''),
            'mime' => $mime, 'size' => $size, 'status' => MediaStatus::AwaitingUpload,
            'position' => $module->sources()->count() + 1, 'created_by' => $actor->id,
        ]);

        $this->audit->record('module.source_started', self::AUDIT_SUBJECT, (string) $module->id, ['role' => $role->value, 'name' => $source->original_name], $actor, $owner);

        $signed = LessonFiles::disk()->temporaryUploadUrl($path, now()->addMinutes(config()->integer('reos.content.upload_minutes')), ['ContentType' => $mime]);
        $headers = ['Content-Type' => $mime];

        foreach ((array) ($signed['headers'] ?? []) as $header => $value) {
            if (is_string($header) && strtolower($header) !== 'host') {
                $headers[$header] = is_array($value) ? implode(', ', array_filter($value, is_string(...))) : (is_scalar($value) ? (string) $value : '');
            }
        }

        return ['source' => $source, 'upload' => ['method' => 'PUT', 'url' => (string) $signed['url'], 'headers' => $headers]];
    }

    /** The browser says the upload finished: check it, then queue the scan and reading. */
    public function complete(ModuleSource $source, User $actor): ModuleSource
    {
        if ($source->status !== MediaStatus::AwaitingUpload) {
            throw ValidationException::withMessages(['file' => __('content.files.errors.done')]);
        }

        $disk = LessonFiles::disk();

        if (! $disk->exists($source->path)) {
            throw ValidationException::withMessages(['file' => __('content.files.errors.missing')]);
        }

        $reason = match (true) {
            $disk->size($source->path) !== $source->size => 'content.files.failed.size',
            ! MediaKind::looksLike($source->extension(), self::head($disk, $source->path)) => 'content.files.failed.type',
            default => null,
        };

        $this->audit->record('module.source_added', self::AUDIT_SUBJECT, (string) $source->course_module_id, [
            'role' => $source->role->value, 'name' => $source->original_name, 'refused' => $reason,
        ], $actor, $source->module->course->organisation_id);

        if ($reason !== null) {
            $disk->delete($source->path);
            $source->forceFill(['status' => MediaStatus::Rejected, 'failure' => $reason])->save();

            return $source;
        }

        $source->forceFill(['status' => MediaStatus::Processing, 'failure' => null])->save();
        ProcessModuleSource::dispatch($source->id);

        return $source;
    }

    /** @param  (Closure(int): void)|null  $retry */
    public function process(ModuleSource $source, ?Closure $retry = null): void
    {
        if ($source->status !== MediaStatus::Processing) {
            return;
        }

        $disk = LessonFiles::disk();

        if ($source->scanned_at === null) {
            $stream = $disk->readStream($source->path);

            if (! is_resource($stream)) {
                $source->forceFill(['status' => MediaStatus::Failed, 'failure' => 'content.files.failed.missing'])->save();

                return;
            }

            try {
                $result = $this->scanner->scan($stream);
            } finally {
                fclose($stream);
            }

            if ($result === ScanResult::Infected) {
                $disk->delete($source->path);
                $source->forceFill(['status' => MediaStatus::Rejected, 'failure' => 'content.files.failed.infected'])->save();

                return;
            }

            if ($result === ScanResult::Unavailable) {
                $retry === null
                    ? $source->forceFill(['status' => MediaStatus::Failed, 'failure' => 'content.files.failed.scanner'])->save()
                    : $retry(300);

                return;
            }

            $source->forceFill(['scanned_at' => now()])->save();
        }

        try {
            match ($source->kind) {
                MediaKind::Video => $source->forceFill(['status' => MediaStatus::Ready])->save(),
                MediaKind::Captions => $this->captions($source, $disk),
                MediaKind::Slides, MediaKind::Document => $this->read($source, $disk),
            };
        } catch (Throwable $e) {
            report($e);
            $source->forceFill(['status' => MediaStatus::Failed, 'failure' => 'content.files.failed.unreadable'])->save();
        }
    }

    public function failed(int $sourceId, string $reason): void
    {
        ModuleSource::query()->whereKey($sourceId)->where('status', MediaStatus::Processing)->update(['status' => MediaStatus::Failed->value, 'failure' => $reason]);
    }

    public function remove(ModuleSource $source, User $actor): void
    {
        LessonFiles::disk()->delete($source->path);
        $source->delete();
        $this->audit->record('module.source_removed', self::AUDIT_SUBJECT, (string) $source->course_module_id, [
            'role' => $source->role->value, 'name' => $source->original_name,
        ], $actor, $source->module->course->organisation_id);
    }

    /**
     * The words of a module's sources, in order, for building lessons and AI drafting (A7.3).
     *
     * @return list<array{source: string, page: int|null, text: string}>
     */
    public function passages(CourseModule $module): array
    {
        $passages = [];

        foreach ($module->sources()->where('status', MediaStatus::Ready->value)->get() as $source) {
            foreach ($source->sections ?? [] as $section) {
                $passages[] = ['source' => $source->original_name, 'page' => $section['page'], 'text' => $section['text']];
            }
        }

        return $passages;
    }

    private function read(ModuleSource $source, FilesystemAdapter $disk): void
    {
        $local = tempnam(sys_get_temp_dir(), 'reos-source-');

        if ($local === false) {
            throw new RuntimeException('No temporary file.');
        }

        try {
            $in = $disk->readStream($source->path);
            $out = fopen($local, 'wb');

            if (! is_resource($in) || $out === false) {
                throw new RuntimeException('The file could not be copied for reading.');
            }

            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
            $sections = $this->extractor->extract($local, $source->extension());
        } finally {
            @unlink($local);
        }

        $this->ready($source, array_map(static fn (Section $s): array => ['page' => $s->page, 'text' => $s->text], $sections));
    }

    private function captions(ModuleSource $source, FilesystemAdapter $disk): void
    {
        $vtt = (string) $disk->get($source->path);
        $cues = Captions::cues($vtt);

        if ($cues === []) {
            $source->forceFill(['status' => MediaStatus::Rejected, 'failure' => 'content.files.failed.noCaptions'])->save();

            return;
        }

        $source->forceFill(['transcript' => $vtt])->save();
        $this->ready($source, [['page' => null, 'text' => implode(' ', array_column($cues, 'text'))]]);
    }

    /** @param  list<array{page: int|null, text: string}>  $sections */
    private function ready(ModuleSource $source, array $sections): void
    {
        $words = array_sum(array_map(static fn (array $s): int => str_word_count($s['text']), $sections));

        $source->forceFill([
            'sections' => $sections,
            'pages' => count(array_filter($sections, static fn (array $s): bool => $s['page'] !== null)) ?: null,
            'words' => $words,
            'status' => MediaStatus::Ready,
        ])->save();
    }

    private static function head(FilesystemAdapter $disk, string $path): string
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
