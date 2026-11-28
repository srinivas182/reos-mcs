import type { CategoryCard, Joining } from './types';

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
