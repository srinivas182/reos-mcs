/**
 * REOS's built-in understanding of a spoken to-do (B4.3): "Remind me to call
 * Maria at 4" → title "Call Maria", Maria, today 4 PM, remind.
 *
 * It runs on the phone when there's no signal (so a reminder with a clear
 * time is set at once), and on the server when AI is off or fails. The
 * server's PHP copy (`App\Voice\DateParser`) passes the same phrase set
 * (`phrases.json`). Claude on Amazon Bedrock does the rest: several items,
 * notes and harder dates.
 *
 * Rules worth knowing:
 * - A bare hour from 1 to 7 means afternoon ("at 4" → 4 PM); 8 to 11 means
 *   morning; 12 is noon. "am", "pm", "de la tarde" and the like override it.
 * - A time with no date that has already passed today means tomorrow.
 * - A weekday means the next one ("Friday" on a Friday is a week later).
 * - A date alone is a due date without a reminder; any time sets one, and
 *   "remind me" with only a date reminds at 9 in the morning.
 */

export type Language = 'en' | 'es';

export type Due = { date: string; time: string | null };

export type ParsedItem = {
    title: string;
    /** Names to match against the person's contacts. */
    people: string[];
    due: Due | null;
    remind: boolean;
};

/** The speaker's local wall time ("2026-11-21T10:00"). */
export type LocalNow = string;

type Wall = { y: number; m: number; d: number; h: number; min: number };

const WEEKDAYS: Record<Language, string[]> = {
    en: ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
    es: ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'],
};

const MONTHS: Record<Language, string[]> = {
    en: ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'],
    es: ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
};

const LEADS: Record<Language, RegExp> = {
    en: /^(?:please\s+)?(?:remind me to|remind me|don'?t forget to|i need to|i have to|add a to-?do to|to-?do:?)\s+/i,
    es: /^(?:por favor\s+)?(?:recu[ée]rdame(?: que)?|recordarme|no olvides|tengo que|necesito|agrega una tarea para)\s+/i,
};

const REMIND_LEAD: Record<Language, RegExp> = {
    en: /^(?:please\s+)?remind me/i,
    es: /^(?:por favor\s+)?(?:recu[ée]rdame|recordarme)/i,
};

/** Verbs after which a capitalised name is a person to link. */
const PERSON_VERBS: Record<Language, RegExp> = {
    en: /\b(?:call(?: back)?|text|email|e-mail|meet|see|ping|message|follow up with|with)\s+((?:\p{Lu}[\p{L}'’-]+)(?:\s+\p{Lu}[\p{L}'’-]+)?)/gu,
    es: /\b(?:llamar a|escribir a|mandar(?: un)? mensaje a|ver a|reunirme con|con)\s+((?:\p{Lu}[\p{L}'’-]+)(?:\s+\p{Lu}[\p{L}'’-]+)?)/gu,
};

const pad = (n: number) => String(n).padStart(2, '0');

function wallOf(now: LocalNow): Wall {
    const [date = '', time = '00:00'] = now.split('T');
    const [y = 1970, m = 1, d = 1] = date.split('-').map(Number);
    const [h = 0, min = 0] = time.split(':').map(Number);

    return { y, m, d, h, min };
}

function addDays(w: Wall, days: number): Wall {
    const at = new Date(Date.UTC(w.y, w.m - 1, w.d + days));

    return { ...w, y: at.getUTCFullYear(), m: at.getUTCMonth() + 1, d: at.getUTCDate() };
}

function weekday(w: Wall): number {
    return new Date(Date.UTC(w.y, w.m - 1, w.d)).getUTCDay();
}

const dateOf = (w: Wall) => `${w.y}-${pad(w.m)}-${pad(w.d)}`;

/** Removes a matched phrase from the working text. */
function cut(text: string, match: RegExpExecArray | null): string {
    return match ? `${text.slice(0, match.index)} ${text.slice(match.index + match[0].length)}` : text;
}

/** An hour said without am or pm: 1–7 afternoon, 8–11 morning, 12 noon. */
function guessHour(hour: number): number {
    return hour >= 1 && hour <= 7 ? hour + 12 : hour;
}

export function parse(text: string, language: Language, now: LocalNow): ParsedItem {
    const today = wallOf(now);
    let rest = ` ${text.trim().replace(/[.!?]+$/, '')} `;
    let day: Wall | null = null;
    let time: { h: number; min: number } | null = null;
    let relative = false;

    const remindLead = REMIND_LEAD[language].test(text.trim());
    rest = ` ${rest.trim().replace(LEADS[language], '')} `;

    const take = (pattern: RegExp): RegExpExecArray | null => {
        const match = pattern.exec(rest);
        rest = cut(rest, match);

        return match;
    };

    if (language === 'en') {
        // "in 2 hours", "in 30 minutes"
        const span = take(/\s+in\s+(\d+|an?|one|two|three)\s+(hours?|minutes?|mins?)\b/i);
        if (span) {
            const words: Record<string, number> = { a: 1, an: 1, one: 1, two: 2, three: 3 };
            const n = Number(span[1]) || words[(span[1] ?? '').toLowerCase()] || 1;
            const minutes = /^h/i.test(span[2] ?? '') ? n * 60 : n;
            const at = new Date(Date.UTC(today.y, today.m - 1, today.d, today.h, today.min + minutes));
            day = { y: at.getUTCFullYear(), m: at.getUTCMonth() + 1, d: at.getUTCDate(), h: 0, min: 0 };
            time = { h: at.getUTCHours(), min: at.getUTCMinutes() };
            relative = true;
        }

        if (take(/\s+(?:on\s+)?the day after tomorrow\b/i)) day = addDays(today, 2);
        else if (take(/\s+tomorrow\b/i)) day = addDays(today, 1);
        else if (take(/\s+today\b/i)) day = today;

        const part = take(/\s+(tonight|this evening|this afternoon|this morning|in the morning|in the afternoon|in the evening)\b/i);
        const partHour: Record<string, number> = { tonight: 19, 'this evening': 18, 'in the evening': 18, 'this afternoon': 15, 'in the afternoon': 15, 'this morning': 9, 'in the morning': 9 };

        const next = take(new RegExp(`\\s+(?:on\\s+)?(?:next\\s+|this\\s+)?(${WEEKDAYS.en.join('|')})\\b`, 'i'));
        if (next) {
            const target = WEEKDAYS.en.indexOf((next[1] ?? '').toLowerCase());
            day = addDays(today, ((target - weekday(today) + 7) % 7) || 7);
        } else if (take(/\s+next week\b/i)) {
            day = addDays(today, ((1 - weekday(today) + 7) % 7) || 7);
        }

        const dated = take(new RegExp(`\\s+(?:on\\s+)?(${MONTHS.en.join('|')})\\s+(\\d{1,2})(?:st|nd|rd|th)?\\b`, 'i'));
        if (dated) {
            const month = MONTHS.en.indexOf((dated[1] ?? '').toLowerCase()) + 1;
            const dayNumber = Number(dated[2]);
            const year = month < today.m || (month === today.m && dayNumber < today.d) ? today.y + 1 : today.y;
            day = { ...today, y: year, m: month, d: dayNumber };
        }

        const noon = take(/\s+at\s+noon\b/i);
        const clock = noon ? null : take(/\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(a\.?m\.?|p\.?m\.?)?(?=\s|$)/i);
        if (noon) {
            time = { h: 12, min: 0 };
        } else if (clock) {
            let h = Number(clock[1]);
            const min = Number(clock[2] ?? 0);
            const meridiem = (clock[3] ?? '').toLowerCase().replace(/\./g, '');
            if (meridiem === 'pm' && h < 12) h += 12;
            else if (meridiem === 'am' && h === 12) h = 0;
            else if (meridiem === '') h = part ? (partHour[(part[1] ?? '').toLowerCase()] ?? 12) >= 12 && h < 12 ? h + 12 : h : guessHour(h);
            time = { h, min };
        } else if (part && !time) {
            time = { h: partHour[(part[1] ?? '').toLowerCase()] ?? 9, min: 0 };
        }
    } else {
        const span = take(/\s+en\s+(\d+|una?|dos|tres)\s+(horas?|minutos?)\b/i);
        if (span) {
            const words: Record<string, number> = { un: 1, una: 1, dos: 2, tres: 3 };
            const n = Number(span[1]) || words[(span[1] ?? '').toLowerCase()] || 1;
            const minutes = /^h/i.test(span[2] ?? '') ? n * 60 : n;
            const at = new Date(Date.UTC(today.y, today.m - 1, today.d, today.h, today.min + minutes));
            day = { y: at.getUTCFullYear(), m: at.getUTCMonth() + 1, d: at.getUTCDate(), h: 0, min: 0 };
            time = { h: at.getUTCHours(), min: at.getUTCMinutes() };
            relative = true;
        }

        // Times first: "de la mañana" is a time of day, not "tomorrow".
        const clock = take(/\s+a\s+la(?:s)?\s+(\d{1,2})(?::(\d{2}))?(?:\s+(de la mañana|de la tarde|de la noche))?(?=\s|$)/i);
        const noon = take(/\s+al\s+mediod[ií]a\b/i);
        const part = take(/\s+(esta noche|esta tarde|esta mañana|por la mañana|por la tarde|por la noche)\b/i);
        const partHour: Record<string, number> = { 'esta noche': 19, 'por la noche': 19, 'esta tarde': 15, 'por la tarde': 15, 'esta mañana': 9, 'por la mañana': 9 };

        if (take(/\s+pasado mañana\b/i)) day = addDays(today, 2);
        else if (take(/\s+mañana\b/i)) day = addDays(today, 1);
        else if (take(/\s+hoy\b/i)) day = today;

        const next = take(new RegExp(`\\s+(?:el\\s+)?(?:pr[oó]ximo\\s+)?(${WEEKDAYS.es.join('|')})(?:\\s+pr[oó]ximo)?(?=\\s|$)`, 'i'));
        if (next) {
            const target = WEEKDAYS.es.indexOf((next[1] ?? '').toLowerCase());
            day = addDays(today, ((target - weekday(today) + 7) % 7) || 7);
        } else if (take(/\s+la\s+pr[oó]xima\s+semana\b/i)) {
            day = addDays(today, ((1 - weekday(today) + 7) % 7) || 7);
        }

        const dated = take(new RegExp(`\\s+(?:el\\s+)?(\\d{1,2})\\s+de\\s+(${MONTHS.es.join('|')})\\b`, 'i'));
        if (dated) {
            const month = MONTHS.es.indexOf((dated[2] ?? '').toLowerCase()) + 1;
            const dayNumber = Number(dated[1]);
            const year = month < today.m || (month === today.m && dayNumber < today.d) ? today.y + 1 : today.y;
            day = { ...today, y: year, m: month, d: dayNumber };
        }

        if (noon) {
            time = { h: 12, min: 0 };
        } else if (clock) {
            let h = Number(clock[1]);
            const min = Number(clock[2] ?? 0);
            const meridiem = (clock[3] ?? '').toLowerCase();
            if ((meridiem === 'de la tarde' || meridiem === 'de la noche') && h < 12) h += 12;
            else if (meridiem === '') h = guessHour(h);
            time = { h, min };
        } else if (part && !time) {
            time = { h: partHour[(part[1] ?? '').toLowerCase()] ?? 9, min: 0 };
        }
    }

    // "Remind me … on Friday": a reminder needs a time, so 9 in the morning.
    if (remindLead && day && !time) {
        time = { h: 9, min: 0 };
    }

    // A time with no date: today, or tomorrow if it has passed.
    if (time && !day) {
        day = time.h * 60 + time.min <= today.h * 60 + today.min && !relative ? addDays(today, 1) : today;
    }

    const title = rest.replace(/\s+/g, ' ').trim().replace(/[,;:]+$/, '');
    const people: string[] = [];
    // The sentence's own capital ("Call…") isn't a name; names after a verb are.
    const sentence = title.charAt(0).toLocaleLowerCase(language) + title.slice(1);
    for (const match of sentence.matchAll(PERSON_VERBS[language])) {
        const name = (match[1] ?? '').trim();
        if (name && !people.includes(name)) people.push(name);
    }

    return {
        title: title.charAt(0).toLocaleUpperCase(language) + title.slice(1),
        people,
        due: day ? { date: dateOf(day), time: time ? `${pad(time.h)}:${pad(time.min)}` : null } : null,
        remind: time !== null,
    };
}
