/** Communities (B17.2): posts, resources and expert sessions, for verified members. */
export type CommunityPost = {
    id: string;
    author: string;
    body: string;
    pinned: boolean;
    at: string;
    mine: boolean;
};

export type CommunityResource = { id: string; title: string; url: string };

export type CommunitySession = {
    id: string;
    title: string;
    about: string | null;
    format: string;
    starts_at: string;
    minutes: number;
    place: string | null;
    join_url: string | null;
    capacity: number | null;
    price_cents: number;
    ce_course: string | null;
    status: string;
    going: number;
    attended: number;
    /** My RSVP: going, held (waiting for payment) or cancelled. */
    mine: string | null;
};

export type CommunityView = {
    id: string;
    name: string;
    about: string | null;
    owner: string;
    is_owner: boolean;
    member: boolean;
    members: number;
    invite_url: string | null;
    /** The QR code to join (the owner only). */
    qr_url: string | null;
    posts: CommunityPost[];
    resources: CommunityResource[];
    sessions: CommunitySession[];
};

export type CommunityResults = {
    members: number;
    joined_30: number;
    via: { link: number; qr: number };
    posts_30: number;
    sessions: number;
    rsvps: number;
    attended: number;
    show_rate: number;
    top: { title: string; going: number; attended: number }[];
};

export type CommunityCard = {
    id: string;
    name: string;
    owner: string;
    members: number;
    next: { title: string; starts_at: string } | null;
};

export type CommunityInvite = {
    id: string;
    name: string;
    about: string | null;
    owner: string;
    members: number;
    verified: boolean;
    member: boolean;
};

export type RosterRow = { id: number; name: string; attended: boolean };

/** Where a community's actions go (web routes from Wayfinder). */
export type CommunityRoutes = {
    post: string;
    removePost: (id: string) => string;
    pin: (id: string) => string;
    rsvp: (id: string) => string;
    addResource: string;
    removeResource: (id: string) => string;
    schedule: string;
    cancelSession: (id: string) => string;
    roster: (id: string) => string;
    attendance: (id: string) => string;
    newCode: string;
};
