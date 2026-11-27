<?php

declare(strict_types=1);

namespace App\RolePlay;

/**
 * REOS's own scoring (A14.1a), used when Claude isn't configured or
 * doesn't answer: five parts of 20 points from what the agent said.
 *
 * - **Structure:** an introduction, proof (results, data, a plan), and
 *   staying in the conversation.
 * - **Questions:** questions that find out what the person needs.
 * - **Objections:** acknowledging the concern and speaking to it.
 * - **Clarity:** answers neither clipped nor rambling, without fillers.
 * - **The ask:** asking for the next step, best near the end.
 *
 * The three lowest parts become the three fixes.
 *
 * @phpstan-type Dimension 'structure'|'questions'|'objections'|'clarity'|'ask'
 * @phpstan-type Lesson 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'
 * @phpstan-type Scores array{structure: int, questions: int, objections: int, clarity: int, ask: int}
 * @phpstan-type Fix array{dimension: Dimension, text: string, example: string, lesson: Lesson}
 */
final class RolePlayScorer
{
    public const DIMENSIONS = ['structure', 'questions', 'objections', 'clarity', 'ask'];

    /** The lesson each fix points to (the course picks in A14.2 use the same codes). */
    public const LESSONS = [
        'structure' => 'listing_presentation',
        'questions' => 'discovery_questions',
        'objections' => 'objection_handling',
        'clarity' => 'clear_speaking',
        'ask' => 'asking_for_business',
    ];

    private const WORDS = [
        'en' => [
            'questions' => ['what', 'how', 'when', 'why', 'which', 'who', 'would you', 'could you', 'can you', 'do you', 'are you', 'have you', 'is it', 'tell me'],
            'ask' => ['meet', 'appointment', 'schedule', 'sign', 'listing agreement', 'tomorrow', 'this week', 'next week', 'can we', "let's", 'next step', 'come by', 'set up a time', 'book', 'sit down'],
            'acknowledge' => ['understand', 'makes sense', 'i hear you', 'fair', 'good question', 'great question', 'appreciate', 'that is normal', "that's normal"],
            'greeting' => ['hi', 'hello', 'thanks', 'thank you', 'my name', "i'm", 'this is', 'good morning', 'good afternoon'],
            'proof' => ['sold', 'homes', 'data', 'market', 'plan', 'days', 'experience', 'comparable', 'results', 'clients'],
            'fillers' => ['um', 'uh', 'you know', 'basically', 'kind of', 'sort of'],
        ],
        'es' => [
            'questions' => ['qué', 'cómo', 'cuándo', 'por qué', 'cuál', 'quién', 'le gustaría', 'podría', 'puede', 'tiene', 'ha pensado', 'me cuenta'],
            'ask' => ['reunión', 'reunirnos', 'cita', 'agendar', 'programar', 'firmar', 'mañana', 'esta semana', 'la próxima semana', 'podemos', 'siguiente paso', 'pasar a', 'sentarnos'],
            'acknowledge' => ['entiendo', 'comprendo', 'tiene sentido', 'es justo', 'buena pregunta', 'le agradezco', 'es normal'],
            'greeting' => ['hola', 'gracias', 'mi nombre', 'soy', 'buenos días', 'buenas tardes'],
            'proof' => ['vend', 'casas', 'datos', 'mercado', 'plan', 'días', 'experiencia', 'comparables', 'resultados', 'clientes'],
            'fillers' => ['eh', 'este', 'o sea', 'pues', 'tipo'],
        ],
    ];

    /**
     * @param  list<string>  $said  the agent's turns, in order
     * @return array{score: int, scores: Scores, fixes: list<Fix>}
     */
    public function score(string $persona, array $said, string $language): array
    {
        $lang = $language === 'es' ? 'es' : 'en';
        $words = self::WORDS[$lang];
        $turns = array_map(static fn (string $t): string => mb_strtolower(trim($t)), $said);
        $all = implode(' ', $turns);
        $count = static fn (string $text, array $list): int => array_sum(array_map(static fn (string $w): int => self::occurrences($text, $w), $list));
        $has = static fn (string $text, array $list): bool => $count($text, $list) > 0;

        // Structure: an introduction, proof, and staying in the conversation.
        $proof = count(array_filter($words['proof'], static fn (string $w): bool => self::occurrences($all, $w) > 0));
        $structure = ($has($turns[0] ?? '', $words['greeting']) ? 5 : 0) + 5 * min(2, $proof) + (count($turns) >= 2 ? 5 : 0);

        // Questions: question marks, or question words (two at most per answer, as speech has no marks); five points each.
        $marks = max(substr_count($all, '?'), substr_count($all, '¿'));
        $spoken = array_sum(array_map(static fn (string $t): int => min(2, $count($t, $words['questions'])), $turns));
        $questions = min(20, 5 * max($marks, $spoken));

        // Objections: acknowledge it, then speak to this persona's concern.
        $topics = Scenarios::TOPICS[$persona][$lang] ?? [];
        $hit = count(array_filter($topics, static fn (string $w): bool => self::occurrences($all, $w) > 0));
        $objections = min(20, ($has($all, $words['acknowledge']) ? 8 : 0) + 4 * min(3, $hit));

        // Clarity: answers of a useful length, without fillers.
        $lengths = array_map(static fn (string $t): int => count(preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: []), $turns);
        $average = $lengths === [] ? 0 : array_sum($lengths) / count($lengths);
        $base = match (true) {
            $average < 5 => 4,
            $average < 10 => 10,
            $average <= 70 => 20,
            default => 12,
        };
        $clarity = max(0, $base - 2 * $count($all, $words['fillers']));

        // The ask: in the last two answers, or at least somewhere.
        $last = implode(' ', array_slice($turns, -2));
        $ask = $has($last, $words['ask']) ? 20 : ($has($all, $words['ask']) ? 10 : 0);

        $scores = ['structure' => $structure, 'questions' => $questions, 'objections' => $objections, 'clarity' => $clarity, 'ask' => $ask];

        return ['score' => array_sum($scores), 'scores' => $scores, 'fixes' => self::fixes($scores, $language)];
    }

    /**
     * The three lowest parts, with REOS's fix, an example line and a lesson.
     *
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
            'text' => $text ?? $tr("roleplay.fixes.{$dimension}.text"),
            'example' => $example ?? $tr("roleplay.fixes.{$dimension}.example"),
            'lesson' => self::LESSONS[$dimension],
        ];
    }

    /**
     * Matches at the start of a word. Words of four letters or more also
     * match their endings ("price" in "priced", the stem "negotiat" in
     * "negotiate"); shorter ones ("hi", "how", "um") must match whole.
     */
    private static function occurrences(string $text, string $word): int
    {
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($word, '/').(mb_strlen($word) < 4 ? '(?![\p{L}\p{N}])' : '').'/u';

        return preg_match_all($pattern, $text) ?: 0;
    }
}
