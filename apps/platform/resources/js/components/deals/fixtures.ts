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
    kept_files: [
        {
            uuid: '0b8e6d1c-4f2a-4e5b-9c3d-7a1f2e3d4c5b',
            kind: 'signed',
            name: "Seller's Disclosure (signed).pdf",
            size: 182_400,
            added_at: '2026-11-22T16:05:00Z',
            scan: 'clean',
            url: '/deals/files/0b8e6d1c-4f2a-4e5b-9c3d-7a1f2e3d4c5b',
        },
        {
            uuid: '1c9f7e2d-5a3b-4f6c-8d4e-8b2a3f4e5d6c',
            kind: 'upload',
            name: 'Amendment to contract.pdf',
            size: 96_210,
            added_at: '2026-11-23T09:12:00Z',
            scan: 'clean',
            url: '/deals/files/1c9f7e2d-5a3b-4f6c-8d4e-8b2a3f4e5d6c',
        },
    ],
    signatures: [
        {
            uuid: '7d3e1f2a-6b4c-4d5e-9f0a-1b2c3d4e5f6a',
            subject: 'Amendment to contract, 1418 Elm St',
            status: 'sent',
            documents: ['Amendment to contract.pdf'],
            signers: [
                {
                    name: 'Ana Ruiz',
                    email: 'ana@example.com',
                    routing_order: 1,
                    status: 'delivered',
                    at: '2026-11-23T10:02:00Z',
                },
                {
                    name: 'Luis Ruiz',
                    email: 'luis@example.com',
                    routing_order: 2,
                    status: 'waiting',
                    at: null,
                },
            ],
            sent_at: '2026-11-23T09:30:00Z',
            completed_at: null,
            filed_in: null,
        },
        {
            uuid: '2a4b6c8d-0e1f-4a2b-8c3d-4e5f6a7b8c9d',
            subject: "Seller's Disclosure",
            status: 'completed',
            documents: ["Seller's Disclosure.pdf"],
            signers: [
                {
                    name: 'Ana Ruiz',
                    email: 'ana@example.com',
                    routing_order: 1,
                    status: 'signed',
                    at: '2026-11-22T16:00:00Z',
                },
            ],
            sent_at: '2026-11-22T14:00:00Z',
            completed_at: '2026-11-22T16:00:00Z',
            filed_in: null,
        },
    ],
    can_sign: true,
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
    kept_files: [],
    signatures: [],
    can_sign: false,
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
