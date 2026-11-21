/**
 * How the public site names the live states. With one live state it is named
 * ("The operating system for Texas real estate careers"); with several, the
 * wording is general and the states are listed ("Texas and Florida").
 * Per-state pages (reos.com/tx) arrive with the state switcher in Sprint 5.
 */
export type LiveStates = {
    /** The only live state, or null when there are several (or none). */
    single: string | null;
    /** Every live state as a sentence list: "Texas, Florida and Georgia". */
    list: string;
    count: number;
};

export function liveStateWording(
    liveStates: readonly string[],
    language: string,
): LiveStates {
    const list =
        typeof Intl.ListFormat === 'function'
            ? new Intl.ListFormat(language, {
                  style: 'long',
                  type: 'conjunction',
              }).format(liveStates)
            : liveStates.join(', ');

    return {
        single: liveStates.length === 1 ? liveStates[0] : null,
        list,
        count: liveStates.length,
    };
}
