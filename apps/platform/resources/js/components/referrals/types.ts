export type ReferralStatus =
    | 'sent'
    | 'emailed'
    | 'accepted'
    | 'declined'
    | 'closed';

export type Referral = {
    id: string;
    direction: 'sent' | 'received';
    status: ReferralStatus;
    kind: 'buyer' | 'seller';
    client: { name: string; email: string | null; phone: string | null };
    area: string | null;
    note: string | null;
    fee_percent: number | null;
    from: { name: string; broker: string | null };
    to: {
        name: string;
        email: string;
        broker: string | null;
        on_reos: boolean;
    };
    contact: string | null;
    sent_at: string;
    answered_at: string | null;
    closed_at: string | null;
};

export type InviteSummary = {
    code: string;
    link: string;
    points_each: number;
    earned: number;
    invited: { name: string; joined_at: string; licensed: boolean }[];
    invited_by: string | null;
    can_claim: boolean;
};

/** Where the view sends referrals and answers them. */
export type ReferralRoutes = {
    create: string;
    accept: (id: string) => string;
    decline: (id: string) => string;
    close: (id: string) => string;
    contact: (uuid: string) => string;
};
