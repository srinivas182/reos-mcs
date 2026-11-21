<?php

declare(strict_types=1);

namespace App\Voice;

use Carbon\CarbonImmutable;

/**
 * REOS's built-in understanding of a spoken to-do (B4.3, ADR 0018): "Remind
 * me to call Maria at 4" → "Call Maria", Maria, today at 4 PM, remind.
 *
 * Used when AI is off, out of credits or fails. The phone runs the same
 * rules in TypeScript (`@reos/voice`) when there's no signal; both pass
 * `packages/voice/src/phrases.json`.
 *
 * - A bare hour from 1 to 7 means afternoon; 8 to 11 morning; 12 noon.
 * - A time with no date that has passed today means tomorrow.
 * - A weekday means the next one (a week on, if it's today).
 * - A date alone is due without a reminder, unless the person said "remind
 *   me", which reminds at 9 in the morning.
 */
final class DateParser
{
    private const WEEKDAYS = [
        'en' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
        'es' => ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'],
    ];

    private const MONTHS = [
        'en' => ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'],
        'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
    ];

    private const LEADS = [
        'en' => "/^(?:please\\s+)?(?:remind me to|remind me|don'?t forget to|i need to|i have to|add a to-?do to|to-?do:?)\\s+/iu",
        'es' => '/^(?:por favor\s+)?(?:recu[ée]rdame(?: que)?|recordarme|no olvides|tengo que|necesito|agrega una tarea para)\s+/iu',
    ];

    private const REMIND_LEADS = [
        'en' => '/^(?:please\s+)?remind me/iu',
        'es' => '/^(?:por favor\s+)?(?:recu[ée]rdame|recordarme)/iu',
    ];

    private const PERSON_VERBS = [
        'en' => "/\\b(?:call(?: back)?|text|email|e-mail|meet|see|ping|message|follow up with|with)\\s+((?:\\p{Lu}[\\p{L}'’-]+)(?:\\s+\\p{Lu}[\\p{L}'’-]+)?)/u",
        'es' => "/\\b(?:llamar a|escribir a|mandar(?: un)? mensaje a|ver a|reunirme con|con)\\s+((?:\\p{Lu}[\\p{L}'’-]+)(?:\\s+\\p{Lu}[\\p{L}'’-]+)?)/u",
    ];

    /**
     * @param  'en'|'es'  $language
     * @param  CarbonImmutable  $now  The speaker's local time (its zone is theirs).
     * @return array{title: string, people: list<string>, due: array{date: string, time: string|null}|null, remind: bool}
     */
    public static function parse(string $text, string $language, CarbonImmutable $now): array
    {
        $today = $now->startOfMinute();
        $rest = ' '.(string) preg_replace(self::LEADS[$language], '', trim((string) preg_replace('/[.!?]+$/u', '', trim($text)))).' ';
        $remindLead = preg_match(self::REMIND_LEADS[$language], trim($text)) === 1;
        $day = null;
        $time = null;
        $relative = false;

        $take = static function (string $pattern) use (&$rest): ?array {
            if (preg_match($pattern, $rest, $match, PREG_OFFSET_CAPTURE) !== 1) {
                return null;
            }

            $whole = $match[0][0];
            $at = $match[0][1];
            $rest = substr($rest, 0, $at).' '.substr($rest, $at + strlen($whole));

            return array_map(static fn (array $group): string => $group[0], $match);
        };

        $words = ['a' => 1, 'an' => 1, 'one' => 1, 'two' => 2, 'three' => 3, 'un' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3];
        $span = $take($language === 'en'
            ? '/\s+in\s+(\d+|an?|one|two|three)\s+(hours?|minutes?|mins?)\b/iu'
            : '/\s+en\s+(\d+|una?|dos|tres)\s+(horas?|minutos?)\b/iu');
        if ($span !== null) {
            $n = is_numeric($span[1]) ? (int) $span[1] : ($words[mb_strtolower($span[1])] ?? 1);
            $at = $today->addMinutes(str_starts_with(mb_strtolower($span[2]), 'h') ? $n * 60 : $n);
            $day = $at->startOfDay();
            $time = [(int) $at->format('G'), (int) $at->format('i')];
            $relative = true;
        }

        if ($language === 'en') {
            if ($take('/\s+(?:on\s+)?the day after tomorrow\b/iu') !== null) {
                $day = $today->addDays(2);
            } elseif ($take('/\s+tomorrow\b/iu') !== null) {
                $day = $today->addDay();
            } elseif ($take('/\s+today\b/iu') !== null) {
                $day = $today;
            }

            $part = $take('/\s+(tonight|this evening|this afternoon|this morning|in the morning|in the afternoon|in the evening)\b/iu');
            $partHours = ['tonight' => 19, 'this evening' => 18, 'in the evening' => 18, 'this afternoon' => 15, 'in the afternoon' => 15, 'this morning' => 9, 'in the morning' => 9];
            $partHour = $part === null ? null : ($partHours[mb_strtolower($part[1])] ?? null);

            $day = self::weekdayOrWeek($take, 'en', $today) ?? $day;

            $dated = $take('/\s+(?:on\s+)?('.implode('|', self::MONTHS['en']).')\s+(\d{1,2})(?:st|nd|rd|th)?\b/iu');
            if ($dated !== null) {
                $day = self::dateOf($today, (int) array_search(mb_strtolower($dated[1]), self::MONTHS['en'], true) + 1, (int) $dated[2]);
            }

            $noon = $take('/\s+at\s+noon\b/iu');
            $clock = $noon === null ? $take('/\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(a\.?m\.?|p\.?m\.?)?(?=\s|$)/iu') : null;

            if ($noon !== null) {
                $time = [12, 0];
            } elseif ($clock !== null) {
                $hour = (int) $clock[1];
                $meridiem = str_replace('.', '', mb_strtolower($clock[3] ?? ''));
                if ($meridiem === 'pm' && $hour < 12) {
                    $hour += 12;
                } elseif ($meridiem === 'am' && $hour === 12) {
                    $hour = 0;
                } elseif ($meridiem === '') {
                    $hour = $partHour !== null ? ($partHour >= 12 && $hour < 12 ? $hour + 12 : $hour) : self::guessHour($hour);
                }
                $time = [$hour, (int) ($clock[2] ?? 0)];
            } elseif ($partHour !== null && $time === null) {
                $time = [$partHour, 0];
            }
        } else {
            // Times first: "de la mañana" is a time of day, not "tomorrow".
            $clock = $take('/\s+a\s+las?\s+(\d{1,2})(?::(\d{2}))?(?:\s+(de la mañana|de la tarde|de la noche))?(?=\s|$)/iu');
            $noon = $take('/\s+al\s+mediod[ií]a\b/iu');
            $part = $take('/\s+(esta noche|esta tarde|esta mañana|por la mañana|por la tarde|por la noche)\b/iu');
            $partHours = ['esta noche' => 19, 'por la noche' => 19, 'esta tarde' => 15, 'por la tarde' => 15, 'esta mañana' => 9, 'por la mañana' => 9];

            if ($take('/\s+pasado mañana\b/iu') !== null) {
                $day = $today->addDays(2);
            } elseif ($take('/\s+mañana\b/iu') !== null) {
                $day = $today->addDay();
            } elseif ($take('/\s+hoy\b/iu') !== null) {
                $day = $today;
            }

            $day = self::weekdayOrWeek($take, 'es', $today) ?? $day;

            $dated = $take('/\s+(?:el\s+)?(\d{1,2})\s+de\s+('.implode('|', self::MONTHS['es']).')\b/iu');
            if ($dated !== null) {
                $day = self::dateOf($today, (int) array_search(mb_strtolower($dated[2]), self::MONTHS['es'], true) + 1, (int) $dated[1]);
            }

            if ($noon !== null) {
                $time = [12, 0];
            } elseif ($clock !== null) {
                $hour = (int) $clock[1];
                $meridiem = mb_strtolower($clock[3] ?? '');
                if (in_array($meridiem, ['de la tarde', 'de la noche'], true) && $hour < 12) {
                    $hour += 12;
                } elseif ($meridiem === '') {
                    $hour = self::guessHour($hour);
                }
                $time = [$hour, (int) ($clock[2] ?? 0)];
            } elseif ($part !== null && $time === null) {
                $time = [$partHours[mb_strtolower($part[1])] ?? 9, 0];
            }
        }

        if ($remindLead && $day !== null && $time === null) {
            $time = [9, 0];
        }

        if ($time !== null && $day === null) {
            $passed = (int) $today->format('G') * 60 + (int) $today->format('i') >= $time[0] * 60 + $time[1];
            $day = $passed && ! $relative ? $today->addDay() : $today;
        }

        $title = trim((string) preg_replace('/\s+/u', ' ', $rest));
        $title = (string) preg_replace('/[,;:]+$/u', '', $title);
        $people = [];
        // The sentence's own capital ("Call…") isn't a name; names after a verb are.
        $sentence = mb_strtolower(mb_substr($title, 0, 1)).mb_substr($title, 1);
        preg_match_all(self::PERSON_VERBS[$language], $sentence, $matches);
        foreach ($matches[1] as $name) {
            $name = trim($name);
            if ($name !== '' && ! in_array($name, $people, true)) {
                $people[] = $name;
            }
        }

        return [
            'title' => mb_strtoupper(mb_substr($title, 0, 1)).mb_substr($title, 1),
            'people' => $people,
            'due' => $day === null ? null : [
                'date' => $day->toDateString(),
                'time' => $time === null ? null : sprintf('%02d:%02d', $time[0], $time[1]),
            ],
            'remind' => $time !== null,
        ];
    }

    /**
     * "on Friday", "next Monday", "el viernes", "next week".
     *
     * @param  callable(string): (array<int, string>|null)  $take
     */
    private static function weekdayOrWeek(callable $take, string $language, CarbonImmutable $today): ?CarbonImmutable
    {
        $names = implode('|', self::WEEKDAYS[$language]);
        $next = $language === 'en'
            ? $take("/\\s+(?:on\\s+)?(?:next\\s+|this\\s+)?({$names})\\b/iu")
            : $take("/\\s+(?:el\\s+)?(?:pr[oó]ximo\\s+)?({$names})(?:\\s+pr[oó]ximo)?(?=\\s|$)/iu");

        if ($next !== null) {
            $target = (int) array_search(mb_strtolower($next[1]), self::WEEKDAYS[$language], true);
            $ahead = ($target - (int) $today->format('w') + 7) % 7;

            return $today->addDays($ahead === 0 ? 7 : $ahead);
        }

        $week = $take($language === 'en' ? '/\s+next week\b/iu' : '/\s+la\s+pr[oó]xima\s+semana\b/iu');
        if ($week !== null) {
            $ahead = (1 - (int) $today->format('w') + 7) % 7;

            return $today->addDays($ahead === 0 ? 7 : $ahead);
        }

        return null;
    }

    /** A month and day said without a year: this year, or next if it has passed. */
    private static function dateOf(CarbonImmutable $today, int $month, int $day): CarbonImmutable
    {
        $passed = $month < (int) $today->format('n') || ($month === (int) $today->format('n') && $day < (int) $today->format('j'));

        return $today->setDate((int) $today->format('Y') + ($passed ? 1 : 0), $month, $day);
    }

    private static function guessHour(int $hour): int
    {
        return $hour >= 1 && $hour <= 7 ? $hour + 12 : $hour;
    }
}
