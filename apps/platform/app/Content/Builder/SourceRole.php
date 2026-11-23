<?php

declare(strict_types=1);

namespace App\Content\Builder;

use App\Content\MediaKind;

/**
 * What a module's source file is to the instructor (A7.2, screen 43):
 * a script, slides, a form, a recorded class, or captions for a recording.
 * Each is read like a lesson file of its kind.
 */
enum SourceRole: string
{
    case Script = 'script';
    case Slides = 'slides';
    case Form = 'form';
    case Recording = 'recording';
    case Captions = 'captions';

    public function kind(): MediaKind
    {
        return match ($this) {
            self::Script, self::Form => MediaKind::Document,
            self::Slides => MediaKind::Slides,
            self::Recording => MediaKind::Video,
            self::Captions => MediaKind::Captions,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $r): string => $r->value, self::cases());
    }
}
