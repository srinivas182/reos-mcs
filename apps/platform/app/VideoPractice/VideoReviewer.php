<?php

declare(strict_types=1);

namespace App\VideoPractice;

/**
 * REOS's own review of a video practice (A14.1b, ADR 0061), from the
 * words and the length: five parts of 20 points, and three fixes from the
 * weakest parts.
 *
 * - **Pace:** 130–160 words a minute is easy to follow.
 * - **Fillers:** "um", "uh", "you know", "basically" … per minute.
 * - **Opening:** your name (and brokerage) in the first sentences.
 * - **Proof:** results, numbers, local knowledge.
 * - **Close:** a clear next step near the end.
 *
 * @phpstan-type Dimension 'pace'|'fillers'|'opening'|'proof'|'close'
 * @phpstan-type Lesson 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'
 * @phpstan-type Scores array{pace: int, fillers: int, opening: int, proof: int, close: int}
 * @phpstan-type Fix array{dimension: Dimension, text: string, example: string, lesson: Lesson}
 * @phpstan-type Filler array{word: string, count: int}
 * @phpstan-type Measures array{words: int, wpm: int, fillers: list<Filler>}
 */
final class VideoReviewer
{
    public const DIMENSIONS = ['pace', 'fillers', 'opening', 'proof', 'close'];

    public const LESSONS = [
        'pace' => 'clear_speaking',
        'fillers' => 'clear_speaking',
        'opening' => 'listing_presentation',
        'proof' => 'listing_presentation',
        'close' => 'asking_for_business',
    ];

    /** Easy-to-follow speaking pace, in words a minute. */
    public const PACE = [130, 160];

    private const WORDS = [
        'en' => [
            'fillers' => ['um', 'uh', 'er', 'you know', 'basically', 'kind of', 'sort of', 'like i said', 'i mean'],
            'opening' => ['my name', "i'm", 'i am', 'this is', 'hi', 'hello', 'welcome'],
            'proof' => ['sold', 'homes', 'clients', 'years', 'days', 'percent', 'market', 'neighborhood', 'data', 'results', 'experience'],
            'close' => ['call', 'text', 'message', 'reach out', 'book', 'schedule', 'visit', 'contact', 'let’s talk', "let's talk", 'link'],
        ],
        'es' => [
            'fillers' => ['eh', 'este', 'o sea', 'pues', 'tipo', 'bueno pues', 'digamos'],
            'opening' => ['mi nombre', 'me llamo', 'soy', 'hola', 'bienvenidos', 'bienvenido'],
            'proof' => ['vend', 'casas', 'clientes', 'años', 'días', 'por ciento', 'mercado', 'vecindario', 'datos', 'resultados', 'experiencia'],
            'close' => ['llame', 'llámeme', 'escríbame', 'mensaje', 'agende', 'programe', 'visite', 'contácteme', 'hablemos', 'enlace'],
        ],
    ];

    /** @return Measures */
    public function measure(string $transcript, int $seconds, string $language): array
    {
        $lang = $language === 'es' ? 'es' : 'en';
        $text = mb_strtolower($transcript);
        $words = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $fillers = [];

        foreach (self::WORDS[$lang]['fillers'] as $filler) {
            $n = self::occurrences($text, $filler);

            if ($n > 0) {
                $fillers[] = ['word' => $filler, 'count' => $n];
            }
        }

        usort($fillers, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return ['words' => $words, 'wpm' => (int) round($words / max(1, $seconds) * 60), 'fillers' => $fillers];
    }

    /**
     * @param  Measures  $measures
     * @return array{score: int, scores: Scores, fixes: list<Fix>}
     */
    public function review(string $transcript, int $seconds, array $measures, string $language): array
    {
        $lang = $language === 'es' ? 'es' : 'en';
        $words = self::WORDS[$lang];
        $text = mb_strtolower($transcript);
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = implode(' ', array_slice($tokens, 0, 25));
        $last = implode(' ', array_slice($tokens, -30));
        $has = static fn (string $part, array $list): bool => array_filter($list, static fn (string $w): bool => self::occurrences($part, $w) > 0) !== [];

        [$low, $high] = self::PACE;
        $wpm = $measures['wpm'];
        $pace = match (true) {
            $wpm >= $low && $wpm <= $high => 20,
            $wpm >= $low - 20 && $wpm <= $high + 20 => 14,
            $wpm >= $low - 40 && $wpm <= $high + 40 => 8,
            default => 4,
        };
        $perMinute = array_sum(array_column($measures['fillers'], 'count')) / max(1, $seconds) * 60;
        $fillers = match (true) {
            $perMinute <= 1 => 20,
            $perMinute <= 3 => 14,
            $perMinute <= 6 => 8,
            default => 2,
        };
        $opening = $has($first, $words['opening']) ? 20 : 6;
        $proof = count(array_filter($words['proof'], static fn (string $w): bool => self::occurrences($text, $w) > 0));
        $proof = $proof >= 2 ? 20 : ($proof === 1 ? 12 : 4);
        $close = $has($last, $words['close']) ? 20 : ($has($text, $words['close']) ? 10 : 0);

        $scores = ['pace' => $pace, 'fillers' => $fillers, 'opening' => $opening, 'proof' => $proof, 'close' => $close];

        return ['score' => array_sum($scores), 'scores' => $scores, 'fixes' => self::fixes($scores, $language)];
    }

    /**
     * @param  Scores  $scores
     * @return list<Fix>
     */
    public static function fixes(array $scores, string $language): array
    {
        $order = self::DIMENSIONS;
        usort($order, static fn (string $a, string $b): int => [$scores[$a], array_search($a, self::DIMENSIONS, true)] <=> [$scores[$b], array_search($b, self::DIMENSIONS, true)]);

        return array_map(static fn (string $d): array => self::fix($d, $language), array_slice($order, 0, 3));
    }

    /**
     * @param  Dimension  $dimension
     * @return Fix
     */
    public static function fix(string $dimension, string $language, ?string $text = null, ?string $example = null): array
    {
        $tr = static function (string $key) use ($language): string {
            $value = __($key, [], $language);

            return is_string($value) ? $value : '';
        };

        return [
            'dimension' => $dimension,
            'text' => $text ?? $tr("videopractice.fixes.{$dimension}.text"),
            'example' => $example ?? $tr("videopractice.fixes.{$dimension}.example"),
            'lesson' => self::LESSONS[$dimension],
        ];
    }

    /** Matches at the start of a word; words under four letters must match whole. */
    private static function occurrences(string $text, string $word): int
    {
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($word, '/').(mb_strlen($word) < 4 ? '(?![\p{L}\p{N}])' : '').'/u';

        return preg_match_all($pattern, $text) ?: 0;
    }
}
