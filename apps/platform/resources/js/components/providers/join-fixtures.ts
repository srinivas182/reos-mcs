import type { CategoryCard, Joining, Onboarding } from './types';

/** Texas categories as Dana Brooks sees them when she joins (B15.1). */
export const categories: CategoryCard[] = [
    {
        key: 'home-stager',
        name: 'Home stager',
        description: 'Staging for listings and open houses.',
        licence: { kind: 'business', label: 'Photo ID and business details' },
        fields: [
            {
                key: 'rooms_per_month',
                label: 'Rooms per month',
                type: 'text',
                options: [],
            },
            {
                key: 'styles',
                label: 'Styles',
                type: 'list',
                options: ['Modern', 'Farmhouse', 'Traditional'],
            },
            {
                key: 'owns_furniture_inventory',
                label: 'Owns furniture inventory',
                type: 'yes_no',
                options: [],
            },
        ],
        areas_on_pro: 3,
        prices: { basic: 2900, pro: 7900, premium: null },
    },
    {
        key: 'loan-officer',
        name: 'Loan officer',
        description: null,
        licence: { kind: 'nmls', label: 'NMLS ID lookup' },
        fields: [
            { key: 'nmls_id', label: 'NMLS ID', type: 'text', options: [] },
        ],
        areas_on_pro: 3,
        prices: { basic: 4900, pro: 14900, premium: 39900 },
    },
];

export const notChosen: Joining = { category: null, fields: {}, missing: [] };

export const stagerChosen: Joining = {
    category: categories[0],
    fields: { rooms_per_month: '12' },
    missing: ['styles', 'owns_furniture_inventory'],
};

/** Maya Chen, a loan officer, halfway through joining (B15.2). */
export const mayaJoining: Onboarding = {
    status: 'joining',
    hidden_reason: null,
    steps: [
        { key: 'welcome', done: true },
        { key: 'category', done: true },
        { key: 'licence', done: true },
        { key: 'areas', done: true },
        { key: 'plan', done: false },
        { key: 'rules', done: false },
    ],
    done: 4,
    joining: {
        category: categories[1],
        fields: { nmls_id: '1234567' },
        missing: [],
    },
    licence: {
        kind: 'nmls',
        licences: [
            {
                id: 7,
                kind: 'nmls',
                number: '1234567',
                status: 'review',
                name_on_record: null,
                licence_type: null,
                expires_on: null,
                checked_via: 'reviewer',
                review_note: null,
                consumer_url:
                    'https://www.nmlsconsumeraccess.org/EntityDetails.aspx/INDIVIDUAL/1234567',
            },
        ],
        business: null,
        verified: false,
    },
    areas: [
        {
            id: 1,
            kind: 'city',
            city: 'Katy',
            postal_code: null,
            radius_miles: null,
            agents: 312,
            providers: 4,
        },
        {
            id: 2,
            kind: 'radius',
            city: null,
            postal_code: '77494',
            radius_miles: 15,
            agents: 540,
            providers: 6,
        },
    ],
    plans: [
        { tier: 'basic', monthly: 4900, areas: 1 },
        { tier: 'pro', monthly: 14900, areas: 3 },
        { tier: 'premium', monthly: 39900, areas: 10 },
    ],
    tier: null,
    rules: {
        version: '2026-11-30',
        keys: ['flat_fee', 'no_payments', 'consent', 'advertising', 'no_spam'],
        accepted_at: null,
        current: false,
    },
};
