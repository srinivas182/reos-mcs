/** QuickBooks Online for the brokerage (B17.3). */
export type AccountRole =
    | 'clearing'
    | 'brokerage_income'
    | 'team_payable'
    | 'agent_payable'
    | 'payouts_expense'
    | 'payouts_clearing';

export type QuickBooksBoard = {
    connection: {
        company: string;
        mode: string;
        status: string;
        connected_at: string | null;
        accounts: Record<string, string>;
        mapped: boolean;
    } | null;
    chart: { id: string; name: string; type: string }[];
    roles: string[];
    pending: {
        split_sheets: {
            id: string;
            property: string;
            agent: string;
            closing_on: string;
            gross_cents: number;
        }[];
        payouts: {
            id: string;
            payee: string;
            sent_on: string;
            amount_cents: number;
        }[];
    };
    exports: {
        kind: string;
        subject: string;
        amount_cents: number;
        status: string;
        ref: string | null;
        error: string | null;
        at: string;
    }[];
};

export type QuickBooksRoutes = {
    connect: string;
    accounts: string;
    export: string;
    disconnect: string;
};
