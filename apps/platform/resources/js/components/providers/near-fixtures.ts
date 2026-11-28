import type { NearYouData, ProviderNetworkInfo } from './types';

/** James in Katy: two providers near him and one in Austin. */
export const jamesNear: NearYouData = {
    cities: ['Katy'],
    categories: [
        { key: 'home-inspector', name: 'Home inspector', count: 1 },
        { key: 'loan-officer', name: 'Loan officer', count: 1 },
        { key: 'title-company', name: 'Title company', count: 0 },
    ],
    providers: [
        {
            id: 4,
            name: 'Patel Inspections',
            category: 'Home inspector',
            category_key: 'home-inspector',
            photo_url: null,
            licence_verified: true,
            areas: ['Katy'],
            response_hours: 2,
            tier: 'pro',
            rating: { average: 4.8, count: 12 },
            near: true,
            connection: null,
        },
        {
            id: 3,
            name: 'Chen Home Lending',
            category: 'Loan officer',
            category_key: 'loan-officer',
            photo_url: null,
            licence_verified: true,
            areas: ['Katy', '15 miles around 77494'],
            response_hours: 1,
            tier: 'basic',
            rating: { average: null, count: 0 },
            near: true,
            connection: '01936b7a-0000-7000-8000-000000000601',
        },
        {
            id: 5,
            name: 'Ortiz Mortgage',
            category: 'Loan officer',
            category_key: 'loan-officer',
            photo_url: null,
            licence_verified: true,
            areas: ['Austin'],
            response_hours: 4,
            tier: 'premium',
            rating: { average: 4.5, count: 2 },
            near: false,
            connection: null,
        },
    ],
};

export const nobodyNear: NearYouData = {
    cities: [],
    categories: jamesNear.categories,
    providers: [],
};

/** Chen Home Lending, not connected yet, with one review. */
export const chenNetwork: ProviderNetworkInfo = {
    rating: { average: 5, count: 1 },
    reviews: [
        {
            id: '01936b7a-0000-7000-8000-000000000801',
            rating: 5,
            body: 'Pre-approvals in a day; my buyers closed on time.',
            agent: 'Maria G.',
            at: '2026-11-20T10:00:00-06:00',
        },
    ],
    connection: null,
    can_connect: true,
    can_review: false,
    review_from: null,
    my_review: null,
};

/** Connected 31 days ago: James can review. */
export const chenReviewable: ProviderNetworkInfo = {
    ...chenNetwork,
    connection: {
        id: '01936b7a-0000-7000-8000-000000000601',
        status: 'accepted',
    },
    can_connect: false,
    can_review: true,
    review_from: '2026-12-30',
};
