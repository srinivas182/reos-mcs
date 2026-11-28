/** Where a payee's Stripe account stands (B5.2b). */
export type PayoutAccount = {
    kind: 'express' | 'standard';
    state: 'ready' | 'onboarding' | 'needsInfo';
    requirements: number;
} | null;

export type PayoutRow = {
    id: string;
    amount: number;
    status: 'sent' | 'failed' | 'sending';
    date: string | null;
};

export type EarningLine = {
    id: number;
    product: string;
    role: 'seller' | 'instructor' | 'mentor';
    amount: number;
    status: 'pending' | 'held' | 'paid' | 'cancelled' | 'reversed';
    date: string | null;
};

/** A date such as "Nov 30, 2026" (dates arrive as YYYY-MM-DD). */
export function shortDate(date: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}
