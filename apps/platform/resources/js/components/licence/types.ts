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
