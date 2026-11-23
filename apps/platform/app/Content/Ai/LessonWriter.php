<?php

declare(strict_types=1);

namespace App\Content\Ai;

/**
 * Writes an AI lesson from a module's sources only (A7.3, ADR 0026) and
 * translates it into Spanish line by line. Claude on Bedrock when it's set
 * up; otherwise the simulator, which builds the lesson from the sources'
 * own sentences so demos and tests need no AI.
 *
 * A brief's passages are numbered from 0; every line, question and
 * flashcard names the passage it comes from.
 *
 * @phpstan-type Passage array{source: string, page: int|null, text: string}
 * @phpstan-type Brief array{title: string, minutes: int, checkin_minutes: int, state: string, passages: list<Passage>}
 * @phpstan-type Draft array{
 *     title: string,
 *     lines: list<array{text: string, source: int}>,
 *     checkins: list<array{after_line: int, prompt: string}>,
 *     questions: list<array{prompt: string, options: list<string>, correct: int, explanation: string, source: int, topic: string}>,
 *     flashcards: list<array{front: string, back: string, source: int}>
 * }
 */
interface LessonWriter
{
    /** "ai" (Claude) or "simulated", for the credit log. */
    public function engine(): string;

    /**
     * @param  Brief  $brief
     * @return Draft
     */
    public function write(array $brief): array;

    /**
     * English texts into Spanish, one for one and in order (formal "usted", the REOS glossary).
     *
     * @param  list<string>  $texts
     * @return list<string>
     */
    public function translate(array $texts): array;
}
