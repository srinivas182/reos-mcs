import type {
    ListingRow,
    ListingsOverview,
    MemberRow,
    PostDraft,
} from './types';

/** James Miller at HAR (journey cast), on the Simulator. */
export const james: MemberRow = {
    mls: 'mls-har',
    short_name: 'HAR',
    member_key: 'HAR-M1',
    name: 'James Miller',
    office: 'Lone Star Realty',
    office_city: 'Katy',
};

const photos = [2, 3, 4, 5].map((n, i) => ({
    url: `/demo/listings/${n}.svg`,
    caption: i === 0 ? 'Front' : null,
}));

export function listing(overrides: Partial<ListingRow> = {}): ListingRow {
    return {
        uuid: '0193b1c2-0000-7000-8000-000000000001',
        mls: 'mls-har',
        short_name: 'HAR',
        listing_id: 'HAR4100137',
        status: 'active',
        role: 'list',
        address: '2418 Willow Bend Dr, Katy, TX 77494',
        city: 'Katy',
        postal_code: '77494',
        price: 489000,
        close_price: null,
        close_date: null,
        beds: 4,
        baths: 3,
        area: 2850,
        price_per_sqft: 172,
        photo: photos[0] ?? null,
        photos,
        listed_on: '2026-11-11',
        days_on_market: 12,
        office: 'Lone Star Realty',
        latitude: 29.7858,
        longitude: -95.8245,
        attribution: 'Listing courtesy of Lone Star Realty. Source: HAR MLS.',
        logo_url: null,
        sample: true,
        can_post: true,
        open_house: {
            starts_at: '2026-11-26T19:00:00+00:00',
            ends_at: '2026-11-26T22:00:00+00:00',
            kind: 'public',
        },
        ...overrides,
    };
}

export function overview(
    overrides: Partial<ListingsOverview> = {},
): ListingsOverview {
    return {
        licensed: true,
        linked: [james],
        candidates: [],
        listings: [
            listing(),
            listing({
                uuid: '0193b1c2-0000-7000-8000-000000000003',
                listing_id: 'HAR4100411',
                status: 'pending',
                role: 'co_list',
                address: '5520 Heron Lake Ln, Houston, TX 77041',
                city: 'Houston',
                price: 412500,
                beds: 4,
                baths: 2.5,
                area: 2410,
                days_on_market: 34,
                open_house: null,
            }),
            listing({
                uuid: '0193b1c2-0000-7000-8000-000000000004',
                listing_id: 'HAR4100548',
                status: 'closed',
                address: '803 Magnolia Trace, Katy, TX 77449',
                price: 338000,
                close_price: 331000,
                close_date: '2026-10-09',
                beds: 3,
                baths: 2,
                area: 1780,
                photo: null,
                photos: [],
                can_post: false,
                days_on_market: 35,
                open_house: null,
            }),
        ],
        sample: true,
        synced_at: '2026-11-24T15:45:00+00:00',
        ...overrides,
    };
}

export function draft(overrides: Partial<PostDraft> = {}): PostDraft {
    return {
        listing: '0193b1c2-0000-7000-8000-000000000001',
        address: '2418 Willow Bend Dr, Katy, TX 77494',
        photos,
        max_photos: 4,
        caption:
            'Just listed: 2418 Willow Bend Dr, Katy, TX 77494, Katy. $489,000 · 4 bd · 3 ba · 2,850 sq ft',
        chosen: photos.slice(0, 2).map((p) => p.url),
        attribution: 'Listing courtesy of Lone Star Realty. Source: HAR MLS.',
        saved_at: null,
        sample: true,
        ...overrides,
    };
}
