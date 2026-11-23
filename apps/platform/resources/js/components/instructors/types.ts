export type InstructorRow = {
    id: number;
    name: string;
    email: string | null;
    status: 'active' | 'invited';
    courses: { id: number; title: string }[];
    approvals: number;
    avatar: {
        status: 'given' | 'withdrawn' | 'ended' | null;
        given_at: string | null;
        lessons_due_by: string | null;
    };
    rating: number | null;
    price: number | null;
};

/** "Mar 1, 2027" from a date or timestamp. */
export function shortDay(value: string | null, language: string): string {
    if (value === null) {
        return '';
    }

    const day = value.length === 10 ? `${value}T00:00:00Z` : value;

    return new Intl.DateTimeFormat(language, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(day));
}
