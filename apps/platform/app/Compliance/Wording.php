<?php

declare(strict_types=1);

namespace App\Compliance;

/**
 * Phrases in advertising wording (B12.1, B13.1; ADR 0057): fair housing
 * (describe the property, not who should live there; 42 U.S.C. §3604(c)
 * and HUD's advertising guidance) and misleading claims (22 TAC
 * §535.155(d)), with **context exceptions** so a place or room name is
 * never flagged ("Indian Wells", "family room", "Christian Street"), and
 * **unclear** phrases that depend on how they're used ("walking distance",
 * "exclusive") — those get a second opinion instead of a block.
 *
 * @phpstan-type Found array{code: 'fair_housing'|'claims'|'banned', phrase: string, fix: string}
 */
final class Wording
{
    /** Wording about who should live there, with words about the home instead. */
    public const FAIR_HOUSING = [
        'perfect for families' => 'roomy layout', 'family-friendly' => 'welcoming', 'family friendly' => 'welcoming', 'ideal for families' => 'roomy layout',
        'great for kids' => 'big backyard', 'no children' => '', 'no kids' => '', 'adults only' => '', 'perfect for couples' => 'inviting home',
        'ideal for singles' => 'easy-care home', 'bachelor pad' => 'stylish home', 'empty nesters' => 'easy-care living', 'exclusive neighborhood' => 'established neighborhood',
        'safe neighborhood' => 'quiet street', 'walking distance to church' => 'close to local amenities', 'christian' => '', 'no section 8' => '',
        'perfecta para familias' => 'distribución amplia', 'ideal para familias' => 'distribución amplia', 'sin niños' => '', 'solo adultos' => '', 'vecindario exclusivo' => 'vecindario establecido',
    ];

    /** Claims an ad can't promise (22 TAC §535.155(d): no misleading advertising). */
    public const CLAIMS = [
        'guaranteed' => '', 'guarantee' => '', 'best price in town' => 'great value', 'lowest price' => 'great value', 'will sell fast' => 'just listed',
        'garantizado' => '', 'el mejor precio' => 'excelente valor',
    ];

    /**
     * Names that contain a flagged word but aren't about people: places,
     * streets and rooms. Matched first and never flagged.
     */
    public const EXCEPTIONS = [
        'indian wells', 'indian hills', 'indian creek', 'indian springs', 'indian lake', 'indian trail', 'indian river',
        'christian street', 'christian st', 'christian county', 'christian brothers',
        'family room', 'family rooms', 'family-room', 'sala familiar',
        'guaranteed rate', 'guaranteed rate field',
    ];

    /**
     * Wording that's fine or not depending on how it's used ("walking
     * distance to the park" is fine; "walking distance to church" isn't).
     * Claude gives a second opinion when it's configured; otherwise the
     * agent is asked to check it.
     */
    public const UNCLEAR = [
        'walking distance', 'exclusive', 'private community', 'perfect for', 'ideal for', 'great for', 'mature', 'senior', 'seniors', 'student',
        'students', 'young professionals', 'retirees', 'handicap', 'able-bodied', 'master bedroom',
        'perfecto para', 'ideal para', 'exclusivo',
    ];

    /**
     * What the wording breaks, with the fix for each.
     *
     * @param  list<string>  $banned  the brokerage's own phrases
     * @return list<Found>
     */
    public static function find(string $text, array $banned = []): array
    {
        $masked = self::mask(mb_strtolower($text));
        $found = [];
        $lists = ['fair_housing' => self::FAIR_HOUSING, 'claims' => self::CLAIMS, 'banned' => array_fill_keys(array_map('mb_strtolower', $banned), '')];

        foreach ($lists as $code => $phrases) {
            foreach ($phrases as $phrase => $fix) {
                if (self::has($masked, (string) $phrase)) {
                    $found[] = ['code' => $code, 'phrase' => (string) $phrase, 'fix' => $fix];
                }
            }
        }

        return $found;
    }

    /**
     * Unclear phrases with the sentence each is in (for the second opinion),
     * leaving out any already flagged.
     *
     * @param  list<string>  $flagged
     * @return list<array{phrase: string, sentence: string}>
     */
    public static function unclear(string $text, array $flagged = []): array
    {
        $lower = mb_strtolower($text);
        $masked = self::mask($lower);
        $out = [];

        foreach (self::UNCLEAR as $phrase) {
            if (! self::has($masked, $phrase) || array_any($flagged, static fn (string $f): bool => str_contains($f, $phrase) || str_contains($phrase, $f))) {
                continue;
            }

            foreach (preg_split('/(?<=[.!?\n])\s+/u', $text) ?: [] as $sentence) {
                if (self::has(self::mask(mb_strtolower($sentence)), $phrase)) {
                    $out[] = ['phrase' => $phrase, 'sentence' => mb_substr(trim($sentence), 0, 300)];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * The text with each flagged phrase swapped or taken out (exceptions left alone).
     *
     * @param  list<Found>  $found
     */
    public static function fix(string $text, array $found): string
    {
        $keep = [];
        $text = self::protect($text, $keep);

        foreach ($found as $issue) {
            $text = (string) preg_replace('/(?<![\p{L}])'.preg_quote($issue['phrase'], '/').'(?![\p{L}])/iu', $issue['fix'], $text);
        }

        $text = strtr($text, $keep);
        // Tidy what removals leave behind.
        $text = (string) preg_replace(['/[ \t]{2,}/u', '/\s+([,.!?])/u', '/,\s*,/u', '/,([.!?])/u', '/^\s*[,.]\s*/mu'], [' ', '$1', ',', '$1', ''], $text);

        return trim($text);
    }

    private static function has(string $lower, string $phrase): bool
    {
        return preg_match('/(?<![\p{L}])'.preg_quote($phrase, '/').'(?![\p{L}])/u', $lower) === 1;
    }

    /** Blanks out exception names so their words aren't matched. */
    private static function mask(string $lower): string
    {
        foreach (self::EXCEPTIONS as $name) {
            $lower = (string) preg_replace('/(?<![\p{L}])'.preg_quote($name, '/').'(?![\p{L}])/u', str_repeat('_', mb_strlen($name)), $lower);
        }

        return $lower;
    }

    /**
     * Swaps exception names for placeholders while fixing, so a fix never touches them.
     *
     * @param  array<string, string>  $keep
     */
    private static function protect(string $text, array &$keep): string
    {
        foreach (self::EXCEPTIONS as $i => $name) {
            $text = (string) preg_replace_callback('/(?<![\p{L}])'.preg_quote($name, '/').'(?![\p{L}])/iu', static function (array $m) use (&$keep, $i): string {
                $token = "\u{E000}{$i}:".count($keep)."\u{E001}";
                $keep[$token] = $m[0];

                return $token;
            }, $text);
        }

        return $text;
    }
}
