<?php

declare(strict_types=1);

namespace App\Content\Player;

/**
 * The instructor without Bedrock keys (demo and tests): answers with the
 * best passage from the course, word for word, and quizzes from the
 * passages. It never makes anything up.
 *
 * @phpstan-import-type Source from AskInstructor
 * @phpstan-import-type Answer from AskInstructor
 */
final class SimulatedInstructor
{
    /**
     * @param  list<Source>  $passages
     * @return Answer
     */
    public static function answer(array $passages, string $mode, ?string $previous, string $language): array
    {
        $best = $passages[0];
        $text = self::sentences($best['text'], 3);
        $answer = match ($mode) {
            'simpler' => self::text('learn.ask.sim.simpler', ['text' => self::sentences($previous ?? $best['text'], 1)], $language),
            'spanish' => self::text('learn.ask.sim.spanish', ['text' => $text], 'es'),
            default => $text,
        };

        return ['answer' => $answer, 'sources' => [$best], 'quiz' => $mode === 'quiz' ? self::quiz($passages, $language) : null, 'engine' => 'simulator', 'found' => true];
    }

    /**
     * Which of these is from the lesson: the best passage's first sentence,
     * against first sentences of the others (or "None of these").
     *
     * @param  list<Source>  $passages
     * @return array{prompt: string, options: list<string>, correct: int, explanation: string}
     */
    private static function quiz(array $passages, string $language): array
    {
        $right = self::sentences($passages[0]['text'], 1);
        $options = [$right];

        foreach (array_slice($passages, 1) as $passage) {
            $sentence = self::sentences($passage['text'], 1);
            if ($sentence !== $right && ! in_array($sentence, $options, true) && count($options) < 3) {
                $options[] = $sentence;
            }
        }
        $options[] = self::text('learn.ask.sim.none', [], $language);

        return [
            'prompt' => self::text('learn.ask.sim.quizPrompt', ['lesson' => $passages[0]['lesson']], $language),
            'options' => $options,
            'correct' => 0,
            'explanation' => self::text('learn.ask.sim.quizWhy', ['lesson' => $passages[0]['lesson']], $language),
        ];
    }

    private static function sentences(string $text, int $count): string
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim((string) preg_replace('/\s+/u', ' ', $text))) ?: [];

        return trim(implode(' ', array_slice($parts, 0, $count)));
    }

    /** @param  array<string, string>  $replace */
    private static function text(string $key, array $replace, string $language): string
    {
        $text = __($key, $replace, $language);

        return is_string($text) ? $text : $key;
    }
}
