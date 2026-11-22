/** Courtesy titles that come before a name ("Dr. Rachel Hughes"). */
const TITLES = new Set(['dr', 'mr', 'mrs', 'ms', 'mx', 'prof', 'rev', 'hon']);

/** The words of a name without a leading title: "Dr. Rachel Hughes" → ["Rachel", "Hughes"]. */
export function nameParts(name: string | null | undefined): string[] {
    const words = (name ?? '').trim().split(/\s+/u).filter(Boolean);

    while (
        words.length > 1 &&
        TITLES.has(words[0].replace(/\.$/u, '').toLowerCase())
    ) {
        words.shift();
    }

    return words;
}

/** The name to greet someone by: "Dr. Rachel Hughes" → "Rachel". */
export function firstName(name: string | null | undefined): string | null {
    return nameParts(name)[0] ?? null;
}
