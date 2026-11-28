import type {
    CommunityCard,
    CommunityInvite,
    CommunityResults,
    CommunityRoutes,
    CommunityView,
    RosterRow,
} from './types';

/** Maya Chen's community (Chen Home Lending, Partner), as she sees it. */
export const mayaCommunity: CommunityView = {
    id: '01a2223d-9b3e-7ee5-91ce-c06e7eb2f500',
    name: 'Chen Home Lending: Buyer Ready',
    about: 'Loan basics for agents in Katy and Cypress: estimates, rates and closing costs, in plain words.',
    owner: 'Maya Chen',
    is_owner: true,
    member: true,
    members: 19,
    invite_url: 'https://reos.com/join/community/k3v9q2m7tz',
    qr_url: null,
    posts: [
        {
            id: 'p1',
            author: 'Maya Chen',
            body: 'Welcome! Next session: reading a loan estimate with your buyers.',
            pinned: true,
            at: '2026-11-20T15:00:00Z',
            mine: true,
        },
        {
            id: 'p2',
            author: 'James Miller',
            body: 'What do buyers ask most about escrow?',
            pinned: false,
            at: '2026-11-22T18:30:00Z',
            mine: false,
        },
    ],
    resources: [
        {
            id: 'r1',
            title: 'Closing cost checklist',
            url: 'https://chenlending.example/checklist',
        },
    ],
    sessions: [
        {
            id: 's1',
            title: 'Reading a loan estimate with your buyers',
            about: 'What each line means and the questions buyers ask.',
            format: 'online',
            starts_at: '2026-12-02T17:00:00Z',
            minutes: 60,
            place: null,
            join_url: 'https://meet.example.com/maya',
            capacity: null,
            price_cents: 0,
            ce_course: null,
            status: 'scheduled',
            going: 18,
            attended: 0,
            mine: null,
        },
        {
            id: 's2',
            title: 'Down payment help in Texas',
            about: null,
            format: 'in_person',
            starts_at: '2026-12-09T23:00:00Z',
            minutes: 90,
            place: '1200 Katy Fwy, Houston',
            join_url: null,
            capacity: 30,
            price_cents: 2500,
            ce_course: 'TREC 41234',
            status: 'scheduled',
            going: 7,
            attended: 0,
            mine: null,
        },
    ],
};

/** The same community as James Miller, a member, sees it. */
export const jamesView: CommunityView = {
    ...mayaCommunity,
    is_owner: false,
    invite_url: null,
    qr_url: null,
    sessions: mayaCommunity.sessions.map((s, i) => ({
        ...s,
        mine: i === 0 ? 'going' : null,
        join_url: i === 0 ? s.join_url : null,
    })),
};

export const mayaResults: CommunityResults = {
    members: 18,
    joined_30: 18,
    via: { link: 12, qr: 6 },
    posts_30: 9,
    sessions: 2,
    rsvps: 25,
    attended: 18,
    show_rate: 72,
    top: [
        {
            title: 'Reading a loan estimate with your buyers',
            going: 18,
            attended: 18,
        },
        { title: 'Down payment help in Texas', going: 7, attended: 0 },
    ],
};

export const jamesCommunities: CommunityCard[] = [
    {
        id: mayaCommunity.id,
        name: mayaCommunity.name,
        owner: 'Maya Chen',
        members: 19,
        next: {
            title: 'Reading a loan estimate with your buyers',
            starts_at: '2026-12-02T17:00:00Z',
        },
    },
];

export const mayaInvite: CommunityInvite = {
    id: mayaCommunity.id,
    name: mayaCommunity.name,
    about: mayaCommunity.about,
    owner: 'Maya Chen',
    members: 19,
    verified: true,
    member: false,
};

export const roster: RosterRow[] = [
    { id: 11, name: 'James Miller', attended: false },
    { id: 12, name: 'Maria Garcia', attended: false },
];

export const noRoutes: CommunityRoutes = {
    post: '#',
    removePost: () => '#',
    pin: () => '#',
    rsvp: () => '#',
    addResource: '#',
    removeResource: () => '#',
    schedule: '#',
    cancelSession: () => '#',
    roster: () => '#',
    attendance: () => '#',
    newCode: '#',
};
