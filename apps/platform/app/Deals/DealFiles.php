<?php

declare(strict_types=1);

namespace App\Deals;

use App\Jobs\ScanDealFile;
use App\Models\Deal;
use App\Models\DealFile;
use App\Services\Content\LessonFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Files kept on a deal (B9.3, ADR 0038): PDFs the agent uploads to send for
 * signature, and the signed copy and certificate filed when an envelope
 * completes. On the content disk, private to the agent, opened with a
 * 30-minute signed link.
 *
 * @phpstan-type FileRow array{uuid: string, kind: string, name: string, size: int, added_at: string|null, scan: string, url: string|null}
 */
final class DealFiles
{
    public const LINK_MINUTES = 30;

    /** 25 MB: DocuSign's limit per document. */
    public const MAX_KB = 25 * 1024;

    public static function upload(Deal $deal, UploadedFile $file): DealFile
    {
        return self::put($deal, 'upload', Str::limit($file->getClientOriginalName(), 200, ''), (string) $file->getContent(), null);
    }

    /** A file REOS made or received (a signed copy, a certificate). */
    public static function put(Deal $deal, string $kind, string $name, string $contents, ?int $signatureRequest): DealFile
    {
        $uuid = (string) Str::uuid7();
        $path = 'deals/'.$deal->uuid.'/'.$uuid.'.pdf';
        LessonFiles::disk()->put($path, $contents);

        $file = DealFile::query()->create([
            'uuid' => $uuid, 'deal_id' => $deal->id, 'user_id' => $deal->user_id, 'organisation_id' => null, 'signature_request_id' => $signatureRequest,
            'kind' => $kind, 'name' => str_ends_with(mb_strtolower($name), '.pdf') ? $name : $name.'.pdf', 'path' => $path, 'size' => strlen($contents), 'scan_status' => 'pending',
        ]);
        ScanDealFile::dispatch($file->id)->afterCommit();

        return $file->refresh();
    }

    public static function contents(DealFile $file): string
    {
        return (string) LessonFiles::disk()->get($file->path);
    }

    public static function remove(DealFile $file): void
    {
        LessonFiles::disk()->delete($file->path);
        $file->delete();
    }

    /** A link that opens a clean file for 30 minutes; null until it's scanned. */
    public static function url(DealFile $file): ?string
    {
        if ($file->scan_status !== 'clean') {
            return null;
        }

        $disk = LessonFiles::disk();

        if ($disk->getAdapter() instanceof LocalFilesystemAdapter) {
            return URL::temporarySignedRoute('deals.files.show', now()->addMinutes(self::LINK_MINUTES), ['file' => $file->uuid]);
        }

        return $disk->temporaryUrl($file->path, now()->addMinutes(self::LINK_MINUTES), [
            'ResponseContentType' => 'application/pdf',
            'ResponseContentDisposition' => 'inline; filename="'.addcslashes($file->name, '"\\').'"',
        ]);
    }

    /** @return FileRow */
    public static function row(DealFile $file): array
    {
        return ['uuid' => $file->uuid, 'kind' => $file->kind, 'name' => $file->name, 'size' => $file->size, 'added_at' => $file->created_at?->toIso8601String(), 'scan' => $file->scan_status, 'url' => self::url($file)];
    }
}
