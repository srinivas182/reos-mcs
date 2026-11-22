<?php

declare(strict_types=1);

namespace App\Content\Knowledge;

/** A passage before it's saved: its words and where in the lesson they are. */
final readonly class Draft
{
    public function __construct(
        public string $kind,
        public string $content,
        public ?string $heading = null,
        public ?int $page = null,
        public ?int $seconds = null,
        public ?int $mediaId = null,
    ) {}

    public function hash(): string
    {
        return hash('sha256', implode("\x1F", [$this->kind, $this->heading ?? '', $this->page ?? '', $this->seconds ?? '', $this->content]));
    }
}
