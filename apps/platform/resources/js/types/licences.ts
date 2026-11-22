/** A state licence (A5.1; LicenceResource). */
export type LicenceStatus =
    | 'active'
    | 'inactive'
    | 'expired'
    | 'pending_review'
    | 'rejected';

export type BrokerRole =
    | 'sponsoring_broker'
    | 'designated_broker'
    | 'delegated_supervisor';

export type Licence = {
    id: number;
    state: { code: string; name: string; live: boolean };
    type: 'sales_agent' | 'broker';
    number: string;
    status: LicenceStatus;
    broker_role: BrokerRole | null;
    holder_name: string | null;
    licensed_on: string | null;
    expires_on: string | null;
    sponsor_name: string | null;
    first_renewal: boolean;
    verified_by: string | null;
    verified_at: string | null;
    review_note: string | null;
    tracked_only: boolean;
    /** CE this period and what's still needed (A5.2); null while in review or without CE rules. */
    ce: CeRequirement | null;
};

/** What CE a licence still needs this period, from the state's rules (A5.2). */
export type CeRequirement = {
    state: string;
    due_on: string | null;
    period_start: string | null;
    days_left: number | null;
    first_renewal: boolean;
    hours_required: number;
    hours_done: number;
    hours_left: number;
    complete: boolean;
    parts: {
        code: string;
        title: string;
        kind: 'mandatory' | 'broker_responsibility' | 'qualifying' | 'elective';
        hours: number;
        hours_done: number;
        done: boolean;
    }[];
    deferral: { fee_usd: number; days: number } | null;
    sources: {
        key: string;
        citation: string;
        url: string | null;
        effective_from: string;
    }[];
};

/** A state a licence can be added in. */
export type LicenceState = { code: string; name: string; live: boolean };
