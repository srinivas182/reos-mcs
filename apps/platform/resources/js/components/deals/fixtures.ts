import type { DealBoard, DealFull } from './types';

/** Maria's deals (screen 14): one tracked in REOS, one from Lone Wolf. */
export const elmStreet: DealFull = {
    id: 1,
    uuid: '9f0c1e7a-2d4b-4c7e-9a51-3b6f0e8d2c11',
    property: '1418 Elm St',
    city: 'Austin',
    side: 'buy',
    stage: 'under_contract',
    price: 425000,
    commission: '12750.00',
    source: 'reos',
    read_only: false,
    external_url: null,
    next: {
        id: 11,
        kind: 'option_period',
        name: 'Option period ends',
        due_on: '2026-11-26',
        days: 3,
        done: false,
    },
    documents: { complete: 0, total: 0, missing: 0 },
    parties: [
        {
            name: 'Ana Ruiz',
            role: 'Buyer',
            email: 'ana@example.com',
            phone: null,
        },
    ],
    files: [],
    dates: [
        {
            id: 11,
            kind: 'option_period',
            name: 'Option period ends',
            due_on: '2026-11-26',
            days: 3,
            done: false,
        },
        {
            id: 12,
            kind: 'closing',
            name: 'Closing',
            due_on: '2026-12-18',
            days: 25,
            done: false,
        },
    ],
};

export const oakCourt: DealFull = {
    id: 2,
    uuid: '4b1d2e3f-5a6b-4c7d-8e9f-0a1b2c3d4e5f',
    property: '9 Oak Ct',
    city: 'Round Rock',
    side: 'sell',
    stage: 'under_contract',
    price: 610000,
    commission: '18300.00',
    source: 'lone-wolf',
    read_only: true,
    external_url: 'https://transact.lwolf.com/transactions/9',
    next: {
        id: 21,
        kind: 'financing',
        name: 'Financing deadline',
        due_on: '2026-12-01',
        days: 8,
        done: false,
    },
    documents: { complete: 1, total: 2, missing: 1 },
    parties: [
        {
            name: 'Dan Whitfield',
            role: 'Seller',
            email: null,
            phone: '+15125550142',
        },
    ],
    files: [
        { name: 'Contract', status: 'complete', url: null },
        { name: 'Seller disclosure', status: 'missing', url: null },
    ],
    dates: [
        {
            id: 21,
            kind: 'financing',
            name: 'Financing deadline',
            due_on: '2026-12-01',
            days: 8,
            done: false,
        },
    ],
};

export const dealBoard: DealBoard = {
    active: [
        {
            ...elmStreet,
            id: 3,
            uuid: '7c8d9e0f-1a2b-4c3d-9e4f-5a6b7c8d9e0f',
            property: '77 Pecan Ln',
            side: 'sell',
            stage: 'active',
            price: 389000,
            commission: null,
            next: null,
        },
    ],
    under_contract: [elmStreet, oakCourt],
    closed: [],
};
