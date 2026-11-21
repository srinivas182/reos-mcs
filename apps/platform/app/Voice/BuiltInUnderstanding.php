<?php

declare(strict_types=1);

namespace App\Voice;

use Carbon\CarbonImmutable;

/** One to-do from the built-in date parser: when AI is off, out of credits or fails. */
final readonly class BuiltInUnderstanding implements Understanding
{
    public function understand(string $text, string $language, CarbonImmutable $now): array
    {
        $item = DateParser::parse($text, $language, $now);

        return $item['title'] === '' ? [] : [[...$item, 'notes' => null]];
    }

    public function engine(): string
    {
        return 'parser';
    }
}
