import type { ComplianceResult } from '@/components/compliance/types';

export type FarmArea = {
    id: string;
    name: string;
    city: string | null;
    postal_code: string | null;
    tag: string;
    mailing_address: string;
    recipients: number;
    last_sent_at: string | null;
};

export type FarmStats = {
    active: number;
    pending: number;
    new_listings: number;
    sold: number;
    median_price: number | null;
    median_days: number | null;
    median_sold_price: number | null;
    months_supply: number | null;
    from: string;
    to: string;
};

export type FarmDraft = {
    area: FarmArea;
    stats: FarmStats;
    subject: string;
    body: string;
    attribution: string;
    check: ComplianceResult;
    can_send: boolean;
    next_send_at: string | null;
};

/** Where the view reads and changes farm areas. */
export type FarmRoutes = {
    create: string;
    remove: (id: string) => string;
    draft: (id: string) => string;
    send: (id: string) => string;
    check: string;
};
