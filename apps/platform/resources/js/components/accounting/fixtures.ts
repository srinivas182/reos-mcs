import type { QuickBooksBoard } from './types';

/** Lone Star Realty, connected in the simulator and mapped, with one sheet waiting. */
export const loneStarBooks: QuickBooksBoard = {
    connection: {
        company: 'Lone Star Realty LLC',
        mode: 'simulator',
        status: 'connected',
        connected_at: '2026-11-23T16:00:00Z',
        accounts: {
            clearing: '35',
            brokerage_income: '81',
            team_payable: '33',
            agent_payable: '34',
            payouts_expense: '60',
            payouts_clearing: '36',
        },
        mapped: true,
    },
    chart: [
        { id: '80', name: 'Commission Income', type: 'Income' },
        { id: '81', name: 'Company Dollar', type: 'Income' },
        {
            id: '33',
            name: 'Team Splits Payable',
            type: 'Other Current Liability',
        },
        {
            id: '34',
            name: 'Agent Commissions Payable',
            type: 'Other Current Liability',
        },
        { id: '35', name: 'Commission Clearing', type: 'Bank' },
        { id: '60', name: 'Mentor and Instructor Payouts', type: 'Expense' },
        { id: '36', name: 'Stripe Clearing', type: 'Bank' },
    ],
    roles: [
        'clearing',
        'brokerage_income',
        'team_payable',
        'agent_payable',
        'payouts_expense',
        'payouts_clearing',
    ],
    pending: {
        split_sheets: [
            {
                id: '01a2223d-9b3e-7ee5-91ce-c06e7eb2f600',
                property: '88 Cypress Bend',
                agent: 'Maria Garcia',
                closing_on: '2026-11-21',
                gross_cents: 960000,
            },
        ],
        payouts: [
            {
                id: '01a2223d-9b3e-7ee5-91ce-c06e7eb2f601',
                payee: 'Dana Brooks',
                sent_on: '2026-11-20',
                amount_cents: 6750,
            },
        ],
    },
    exports: [
        {
            kind: 'split_sheet',
            subject: '01a2223d-9b3e-7ee5-91ce-c06e7eb2f602',
            amount_cents: 1275000,
            status: 'posted',
            ref: 'JE-4F2A91C0',
            error: null,
            at: '2026-11-23T16:05:00Z',
        },
    ],
};

export const notConnected: QuickBooksBoard = {
    connection: null,
    chart: [],
    roles: loneStarBooks.roles,
    pending: loneStarBooks.pending,
    exports: [],
};
