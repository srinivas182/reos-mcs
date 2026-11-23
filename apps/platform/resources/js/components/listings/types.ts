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

export type Photo = { url: string; caption: string | null };

/** A listing as the listing service lets this screen show it (B10.2). */
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
    postal_code: string | null;
    price: number | null;
    close_price: number | null;
    close_date: string | null;
    beds: number | null;
    baths: number | null;
    area: number | null;
    price_per_sqft: number | null;
    photo: Photo | null;
    /** Never more than the MLS allows. */
    photos: Photo[];
    listed_on: string | null;
    days_on_market: number | null;
    office: string | null;
    latitude: number | null;
    longitude: number | null;
    /** The MLS's required attribution: show it with the listing. */
    attribution: string;
    logo_url: string | null;
    sample: boolean;
    /** The MLS lets the agent post about it now. */
    can_post: boolean;
    open_house: { starts_at: string; ends_at: string; kind: string } | null;
};

/** A listing post (B10.2): photos within the MLS's limit, and its attribution. */
export type PostDraft = {
    listing: string;
    address: string;
    photos: Photo[];
    max_photos: number;
    caption: string;
    chosen: string[];
    attribution: string;
    saved_at: string | null;
    sample: boolean;
};

export type ListingsOverview = {
    licensed: boolean;
    linked: MemberRow[];
    candidates: MemberRow[];
    listings: ListingRow[];
    sample: boolean;
    synced_at: string | null;
};
