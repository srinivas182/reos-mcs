<?php

declare(strict_types=1);

namespace App\Content;

/**
 * A file attached to a lesson (A5.3). Each kind accepts a few file types,
 * checked by extension, declared type and the file's first bytes (so a
 * renamed program is refused), and has a size limit.
 */
enum MediaKind: string
{
    case Video = 'video';
    case Slides = 'slides';
    case Document = 'document';
    case Captions = 'captions';

    private const MB = 1024 * 1024;

    /** @return array<string, string> extension => MIME type */
    public function types(): array
    {
        return match ($this) {
            self::Video => ['mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'm4v' => 'video/x-m4v', 'webm' => 'video/webm'],
            self::Slides => [
                'pdf' => 'application/pdf',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
            self::Document => [
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            self::Captions => ['vtt' => 'text/vtt'],
        };
    }

    public function maxBytes(): int
    {
        return match ($this) {
            self::Video => 5 * 1024 * self::MB,
            self::Slides, self::Document => 100 * self::MB,
            self::Captions => 2 * self::MB,
        };
    }

    /** Whether the file's first bytes match its extension. */
    public static function looksLike(string $extension, string $head): bool
    {
        return match ($extension) {
            'pdf' => str_starts_with($head, '%PDF-'),
            'pptx', 'docx' => str_starts_with($head, "PK\x03\x04"),
            'mp4', 'mov', 'm4v' => strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp',
            'webm' => str_starts_with($head, "\x1A\x45\xDF\xA3"),
            'vtt' => str_starts_with(ltrim($head, "\xEF\xBB\xBF"), 'WEBVTT'),
            default => false,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
