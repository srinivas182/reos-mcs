<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Jobs\ScanMessageAttachment;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Services\Content\LessonFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Files sent with messages (B6.1b): images and PDFs up to 20 MB, kept on the
 * content disk and virus-scanned (ClamAV, as for lesson files) before any
 * participant can open them. Opened with a short-lived signed link.
 */
final class Attachments
{
    public const LINK_MINUTES = 30;

    /** @param  list<UploadedFile>  $files */
    public static function store(Message $message, array $files): void
    {
        $disk = LessonFiles::disk();

        foreach ($files as $file) {
            $uuid = (string) Str::uuid7();
            $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
            $path = 'messages/'.$message->conversation->uuid.'/'.$uuid.'.'.$extension;
            $disk->putFileAs(dirname($path), $file, basename($path));

            $attachment = MessageAttachment::query()->create([
                'uuid' => $uuid,
                'message_id' => $message->id,
                'path' => $path,
                'name' => Str::limit($file->getClientOriginalName(), 200, ''),
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize() ?: 0,
                'scan_status' => 'pending',
                'created_at' => now(),
            ]);

            ScanMessageAttachment::dispatch($attachment->id)->afterCommit();
        }
    }

    /** Removes the files of a message deleted for everyone. */
    public static function forget(Message $message): void
    {
        $disk = LessonFiles::disk();

        foreach ($message->attachments as $attachment) {
            $disk->delete($attachment->path);
            $attachment->delete();
        }
    }

    /** A link that opens a clean file for 30 minutes; null until it's scanned. */
    public static function url(MessageAttachment $attachment): ?string
    {
        if ($attachment->scan_status !== 'clean') {
            return null;
        }

        $disk = LessonFiles::disk();

        if ($disk->getAdapter() instanceof LocalFilesystemAdapter) {
            return URL::temporarySignedRoute('messages.attachments.show', now()->addMinutes(self::LINK_MINUTES), ['attachment' => $attachment->uuid]);
        }

        return $disk->temporaryUrl($attachment->path, now()->addMinutes(self::LINK_MINUTES), [
            'ResponseContentDisposition' => 'inline; filename="'.addcslashes($attachment->name, '"\\').'"',
        ]);
    }
}
