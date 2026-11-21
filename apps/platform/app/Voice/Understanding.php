<?php

declare(strict_types=1);

namespace App\Voice;

use Carbon\CarbonImmutable;

/**
 * Turns what someone said into items to confirm (B4.3, ADR 0018).
 *
 * Each item: `title`, `notes` (or null), `people` (names to match against
 * contacts), `due` (`date` YYYY-MM-DD and `time` HH:MM or null, in the
 * speaker's time zone; or null) and `remind`.
 *
 * @phpstan-type Item array{title: string, notes: string|null, people: list<string>, due: array{date: string, time: string|null}|null, remind: bool}
 */
interface Understanding
{
    /**
     * @param  'en'|'es'  $language
     * @param  CarbonImmutable  $now  When it was said, in the speaker's time zone.
     * @return list<Item>
     */
    public function understand(string $text, string $language, CarbonImmutable $now): array;

    /** `ai` or `parser`, as the API reports it. */
    public function engine(): string;
}
