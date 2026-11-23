<?php

declare(strict_types=1);

namespace App\Content\Ai;

use App\Enums\QuizTopic;

/**
 * Checks a lesson draft like any input (A7.3): only what fits the shape and
 * points at a real passage survives, with lengths capped. Used on Claude's
 * answer and the simulator's alike.
 *
 * @phpstan-import-type Draft from LessonWriter
 */
final class Drafts
{
    public const MAX_LINES = 80;

    public const MAX_QUESTIONS = 10;

    public const MAX_FLASHCARDS = 16;

    /**
     * @param  array<array-key, mixed>  $input
     * @return Draft
     */
    public static function clean(array $input, int $passages, string $fallbackTitle): array
    {
        $source = static fn (mixed $n): ?int => is_int($n) && $n >= 0 && $n < $passages ? $n : null;
        $text = static fn (mixed $value, int $max): ?string => is_string($value) && trim($value) !== '' ? mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, $max) : null;
        $lines = [];

        foreach (self::list($input['lines'] ?? null) as $line) {
            $words = $text($line['text'] ?? null, 600);
            $from = $source($line['source'] ?? null);

            if ($words !== null && $from !== null && count($lines) < self::MAX_LINES) {
                $lines[] = ['text' => $words, 'source' => $from];
            }
        }

        $checkins = [];
        foreach (self::list($input['checkins'] ?? null) as $checkin) {
            $prompt = $text($checkin['prompt'] ?? null, 200);
            $after = $checkin['after_line'] ?? null;

            if ($prompt !== null && is_int($after) && $after >= 0 && $after < count($lines)) {
                $checkins[] = ['after_line' => $after, 'prompt' => $prompt];
            }
        }

        $topics = array_map(static fn (QuizTopic $t): string => $t->value, QuizTopic::cases());
        $questions = [];
        foreach (self::list($input['questions'] ?? null) as $question) {
            $prompt = $text($question['prompt'] ?? null, 400);
            $options = array_values(array_filter(array_map(static fn (mixed $o): ?string => $text($o, 200), is_array($question['options'] ?? null) ? array_values($question['options']) : [])));
            $correct = $question['correct'] ?? null;
            $from = $source($question['source'] ?? null);

            if ($prompt === null || count($options) < 2 || count($options) > 5 || ! is_int($correct) || $correct < 0 || $correct >= count($options) || $from === null || count($questions) >= self::MAX_QUESTIONS) {
                continue;
            }

            $topic = $question['topic'] ?? null;
            $questions[] = [
                'prompt' => $prompt,
                'options' => $options,
                'correct' => $correct,
                'explanation' => $text($question['explanation'] ?? null, 400) ?? '',
                'source' => $from,
                'topic' => is_string($topic) && in_array($topic, $topics, true) ? $topic : QuizTopic::StateRules->value,
            ];
        }

        $flashcards = [];
        foreach (self::list($input['flashcards'] ?? null) as $card) {
            $front = $text($card['front'] ?? null, 200);
            $back = $text($card['back'] ?? null, 400);
            $from = $source($card['source'] ?? null);

            if ($front !== null && $back !== null && $from !== null && count($flashcards) < self::MAX_FLASHCARDS) {
                $flashcards[] = ['front' => $front, 'back' => $back, 'source' => $from];
            }
        }

        return [
            'title' => $text($input['title'] ?? null, 160) ?? $fallbackTitle,
            'lines' => $lines,
            'checkins' => $checkins,
            'questions' => $questions,
            'flashcards' => $flashcards,
        ];
    }

    /** @return list<array<array-key, mixed>> */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }
}
