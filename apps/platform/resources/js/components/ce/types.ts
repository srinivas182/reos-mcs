import type { CeRequirement } from '@/types';

/** The CE wallet (A11.2): what `CeWallet::wallet()` sends. */
export type CompletionStatus = 'reos_reported' | 'self_reported' | 'on_record';

export type TrecStatus = 'met' | 'outstanding' | 'none' | null;

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
