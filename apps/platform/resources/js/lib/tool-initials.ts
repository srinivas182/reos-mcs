/** Two letters for a tool's tile ("Google Calendar" → "GC"). */
export function toolInitials(name: string): string {
    return name
        .split(/\s+/)
        .filter(
            (word) => /^[A-Za-z]/.test(word) && word.toLowerCase() !== 'and',
        )
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase())
        .join('');
}
