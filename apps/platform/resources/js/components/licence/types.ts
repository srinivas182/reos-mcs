/** The licence journey (A10.3a) as the server presents it (LicenceJourneys). */
export type Portion = 'national' | 'state';

export type PortionResult = {
    passed: boolean | null;
    attempts: number;
    failed: number;
    on: string | null;
};

export type KitItem =
    | 'id'
    | 'confirmation'
    | 'final_simulation'
    | 'flashcards'
    | 'sleep';

export type Journey = {
    state: { code: string; name: string };
    steps: {
        code: string;
        title: string;
        detail: string;
        url: string | null;
        done_on: string | null;
        /** Checked off by REOS (the exam, once both portions are passed). */
        auto: boolean;
    }[];
    window: {
        filed_on: string | null;
        ends_on: string | null;
        days_left: number | null;
        months: number;
    };
    readiness: {
        percent: number | null;
        national: number | null;
        state: number | null;
        line: number;
        ready: boolean;
        simulations: number;
        needed: number;
    };
    exam: {
        vendor: string | null;
        book_url: string | null;
        on: string | null;
        time: string | null;
        centre: string | null;
        days_to: number | null;
        directions_url: string | null;
    };
    kit: { items: { key: KitItem; done: boolean }[]; rules: string | null };
    results: {
        national: PortionResult;
        state: PortionResult;
        can_enter: boolean;
        attempts_allowed: number;
        passed_both: boolean;
    };
    retake: {
        portions: Portion[];
        topics: string[];
        more_education: boolean;
        attempts_left: Partial<Record<Portion, number>>;
    } | null;
    /** After passing: share the news (f05) until shared or skipped (A10.3b). */
    news: 'prompt' | 'shared' | 'skipped' | null;
    /** Broker offers (screen 17). */
    offers: { opted_in: boolean; city: string | null; open: number };
};

/** A pass someone shared (f06). */
export type PassRow = {
    id: number;
    first_name: string;
    city: string | null;
    state: string;
    tip: string | null;
    on: string;
    congrats: number;
    sent: boolean;
    mine: boolean;
};

export type PassFeed = {
    week: number;
    state: string;
    readiness: number | null;
    passes: PassRow[];
};

/** A brokerage's offer (screen 17; recruiting). */
export type OfferRow = {
    id: string;
    brokerage: string;
    office_city: string;
    split_percent: number;
    monthly_fee_cents: number;
    fees: string | null;
    training: string | null;
    mentoring: boolean;
    /** Sponsored AI study help (A17.2): the candidate sees only that it's included. */
    ai_help?: boolean;
    /** The credits, for the brokerage only. */
    ai_credits?: number | null;
    message: string | null;
    status: 'open' | 'accepted' | 'declined' | 'withdrawn';
    rating: number | null;
    ratings: number;
    fit: boolean;
    contact: { name: string; email: string; phone: string | null } | null;
};

export type CandidateOffers = {
    opted_in: boolean;
    city: string | null;
    offers: OfferRow[];
};

/** A day such as "Mon, Dec 14" from "2026-12-14", whatever the viewer's zone. */
export function dayText(day: string, language: string): string {
    return new Intl.DateTimeFormat(language, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${day}T00:00:00Z`));
}

/** Today as "2026-11-23" in the viewer's zone. */
export function todayIso(): string {
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Switching to agent (A10.3c, f03 and 20). */
export type SwitchState = {
    number: string | null;
    check:
        | 'verified'
        | 'inactive'
        | 'name_mismatch'
        | 'not_found'
        | 'no_lookup'
        | null;
    checked_at: string | null;
    watching: boolean;
    record: {
        name: string;
        type: string;
        status: string;
        licensed_on: string | null;
        sponsor: string | null;
    } | null;
    can_switch: boolean;
    broker: { id: string; name: string } | null;
    switched: boolean;
};
