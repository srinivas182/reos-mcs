import type { CeRequirement } from '@/types';

/** The CE wallet (A11.2): what `CeWallet::wallet()` sends. */
export type CompletionStatus = 'reos_reported' | 'self_reported' | 'on_record';

export type TrecStatus = 'met' | 'outstanding' | 'none' | null;

export type ReportingStatus =
    | 'waiting'
    | 'pending_upload'
    | 'submitted'
    | 'reported'
    | 'rejected';
export type StepCode =
    | 'completed'
    | 'final_passed'
    | 'reported'
    | 'member_record'
    | 'certificate';
export type StepState =
    | 'done'
    | 'waiting'
    | 'pending'
    | 'rejected'
    | 'not_connected';

/** A REOS completion's way to TREC (A11.3, screen 104). */
export type Reporting = {
    status: ReportingStatus;
    reference: string | null;
    reason: string | null;
    steps: { code: StepCode; state: StepState }[];
};

export type Certificate = {
    id: number;
    holder: string;
    licence: string | null;
    state_code: string;
    title: string;
    hours: number;
    completed_on: string;
    provider: string | null;
    course_number: string | null;
    status: CompletionStatus;
    issued: boolean;
    reporting: Reporting | null;
};

export type RenewalStep = {
    code: string;
    title: string;
    detail: string;
    url: string | null;
    done: boolean;
};

export type Wallet = {
    licence: {
        id: number;
        state_code: string;
        state_name: string;
        number: string;
        type: string;
        status: string;
        expires_on: string | null;
        sponsor_name: string | null;
    };
    ce: CeRequirement;
    completions: {
        id: number;
        title: string;
        hours: number;
        completed_on: string;
        provider: string | null;
        course_code: string | null;
        status: CompletionStatus;
        certificate: boolean;
        reporting: Reporting | null;
    }[];
    trec: { mce: TrecStatus; education: TrecStatus; checked_at: string | null };
    renewal: {
        due_on: string | null;
        ready: boolean;
        renewed_at: string | null;
        confirmed_at: string | null;
        renewed_until: string | null;
        steps: RenewalStep[];
    };
    buy: {
        price: number;
        product: string;
        amount: number;
        interval: 'month' | 'year' | 'once';
    } | null;
    unlocked: boolean;
};
