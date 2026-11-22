<?php

declare(strict_types=1);

namespace App\Content\Knowledge;

use App\Content\Extraction\Section;
use App\Models\KbPassage;

/**
 * Splits a lesson into short passages that each make sense alone (A5.3):
 * text by heading and paragraph (about 900 characters), slides and pages one
 * at a time, video captions in windows of about 45 seconds. Each passage
 * keeps where it came from, so a search result opens at the right place.
 */
final class Chunker
{
    public const TARGET = 900;

    public const MAX = 1400;

    public const WINDOW_SECONDS = 45;

    /**
     * Lesson text: lines starting with "#" are headings.
     *
     * @return list<Draft>
     */
    public function text(string $body, string $kind = KbPassage::TEXT, ?int $mediaId = null): array
    {
        $drafts = [];
        $heading = null;
        $buffer = '';

        $flush = function () use (&$drafts, &$buffer, &$heading, $kind, $mediaId): void {
            if (trim($buffer) !== '') {
                $drafts[] = new Draft($kind, trim($buffer), $heading, mediaId: $mediaId);
            }

            $buffer = '';
        };

        foreach (preg_split("/\n\s*\n|\n(?=#)/", str_replace("\r\n", "\n", $body)) ?: [] as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            if (preg_match('/^#{1,6}\s+(.+?)(?:\n(.*))?$/s', $block, $match) === 1) {
                $flush();
                $heading = mb_substr(trim($match[1]), 0, 200);
                $block = trim($match[2] ?? '');

                if ($block === '') {
                    continue;
                }
            }

            foreach ($this->pieces($block) as $piece) {
                if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($piece) > self::TARGET) {
                    $flush();
                }

                $buffer .= ($buffer === '' ? '' : "\n\n").$piece;
            }
        }

        $flush();

        return $drafts;
    }

    /**
     * Slides or document pages; a document with no pages (Word) is split like lesson text.
     *
     * @param  list<Section>  $sections
     * @return list<Draft>
     */
    public function sections(array $sections, int $mediaId): array
    {
        $drafts = [];

        foreach ($sections as $section) {
            if ($section->page === null) {
                array_push($drafts, ...$this->text($section->text, KbPassage::SLIDE, $mediaId));

                continue;
            }

            $buffer = '';

            foreach ($this->pieces($section->text) as $piece) {
                if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($piece) > self::MAX) {
                    $drafts[] = new Draft(KbPassage::SLIDE, $buffer, page: $section->page, mediaId: $mediaId);
                    $buffer = '';
                }

                $buffer .= ($buffer === '' ? '' : "\n").$piece;
            }

            if (trim($buffer) !== '') {
                $drafts[] = new Draft(KbPassage::SLIDE, trim($buffer), page: $section->page, mediaId: $mediaId);
            }
        }

        return $drafts;
    }

    /**
     * Video captions in windows that start at a cue.
     *
     * @param  list<array{seconds: int, text: string}>  $cues
     * @return list<Draft>
     */
    public function cues(array $cues, int $mediaId): array
    {
        $drafts = [];
        $start = null;
        $buffer = '';

        foreach ($cues as $cue) {
            if ($start !== null && ($cue['seconds'] - $start >= self::WINDOW_SECONDS || mb_strlen($buffer) >= self::TARGET)) {
                $drafts[] = new Draft(KbPassage::TRANSCRIPT, $buffer, seconds: $start, mediaId: $mediaId);
                $start = null;
                $buffer = '';
            }

            $start ??= $cue['seconds'];
            $buffer .= ($buffer === '' ? '' : ' ').$cue['text'];
        }

        if ($start !== null && trim($buffer) !== '') {
            $drafts[] = new Draft(KbPassage::TRANSCRIPT, $buffer, seconds: $start, mediaId: $mediaId);
        }

        return $drafts;
    }

    /**
     * Paragraphs, with any too long split at sentence ends.
     *
     * @return list<string>
     */
    private function pieces(string $block): array
    {
        $pieces = [];

        foreach (preg_split("/\n+/", $block) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            while (mb_strlen($line) > self::MAX) {
                $cut = mb_strrpos(mb_substr($line, 0, self::TARGET), '. ');
                $cut = $cut === false || $cut < 200 ? self::TARGET : $cut + 1;
                $pieces[] = trim(mb_substr($line, 0, $cut));
                $line = trim(mb_substr($line, $cut));
            }

            $pieces[] = $line;
        }

        return $pieces;
    }
}
