<?php

declare(strict_types=1);

namespace App\Content\Extraction;

/** Text read from a file: one page or slide (`page` from 1), or a whole document (`page` null, "# " headings). */
final readonly class Section
{
    public function __construct(public ?int $page, public string $text) {}
}
