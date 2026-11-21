/**
 * "2 days ago" / "hace 2 días", in the reader's language, starting with a
 * capital letter so it can open a sentence.
 */
export function timeAgo(
    iso: string,
    language: string,
    now: Date = new Date(),
): string {
    const seconds = (new Date(iso).getTime() - now.getTime()) / 1000;
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];

    // Under a minute: "now" / "ahora".
    if (Math.abs(seconds) < 60) {
        const now = new Intl.RelativeTimeFormat(language, {
            numeric: 'auto',
        }).format(0, 'second');

        return now.charAt(0).toLocaleUpperCase(language) + now.slice(1);
    }

    const format = new Intl.RelativeTimeFormat(language, { numeric: 'auto' });
    const [unit, size] = units.find(([, s]) => Math.abs(seconds) >= s) ?? [
        'minute',
        60,
    ];
    const text = format.format(Math.round(seconds / size), unit);

    return text.charAt(0).toLocaleUpperCase(language) + text.slice(1);
}
