<?php

declare(strict_types=1);

namespace App\Content\Knowledge;

/**
 * One search result (A5.3): a passage, the lesson and course it's in, and
 * where to open it (heading, page or second). The snippet marks the
 * matching words with [[ and ]].
 */
final readonly class SearchHit
{
    /** @param  'keyword'|'meaning'|'both'  $match */
    public function __construct(
        public int $passageId,
        public int $lessonId,
        public string $lessonTitle,
        public int $courseId,
        public string $courseTitle,
        public string $kind,
        public ?string $heading,
        public ?int $page,
        public ?int $seconds,
        public string $language,
        public string $snippet,
        public string $match,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'passage_id' => $this->passageId,
            'lesson_id' => $this->lessonId,
            'lesson_title' => $this->lessonTitle,
            'course_id' => $this->courseId,
            'course_title' => $this->courseTitle,
            'kind' => $this->kind,
            'heading' => $this->heading,
            'page' => $this->page,
            'seconds' => $this->seconds,
            'language' => $this->language,
            'snippet' => $this->snippet,
            'match' => $this->match,
        ];
    }
}
