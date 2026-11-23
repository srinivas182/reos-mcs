/** My listings (B10.1): what `MyListings::overview()` sends. */
export type ListingStatus =
    | 'coming_soon'
    | 'active'
    | 'under_contract'
    | 'pending'
    | 'closed';

export type MemberRow = {
    mls: string;
    short_name: string;
    member_key: string;
    name: string;
    office: string | null;
    office_city: string | null;
};

export type ListingRow = {
    uuid: string;
    mls: string;
    short_name: string;
    listing_id: string | null;
    /** A ListingStatus; any other value shows as it came. */
    status: string;
    role: 'list' | 'co_list';
    address: string;
    city: string | null;
    price: number | null;
    close_price: number | null;
    beds: number | null;
    baths: number | null;
    area: number | null;
    photo: { url: string; caption: string | null } | null;
    photos: number;
    listed_on: string | null;
    days_on_market: number | null;
    office: string | null;
    open_house: { starts_at: string; ends_at: string; kind: string } | null;
};

export type ListingsOverview = {
    licensed: boolean;
    linked: MemberRow[];
    candidates: MemberRow[];
    listings: ListingRow[];
    sample: boolean;
    synced_at: string | null;
};
