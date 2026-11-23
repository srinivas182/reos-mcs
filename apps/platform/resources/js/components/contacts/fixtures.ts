import type { ContactInsight, ContactPanelData, ContactRow } from '@/types';

/** James Miller's contacts (demo data): Maria Lopez is in three CRMs and his phone. */
const hoursAgo = (h: number) =>
    new Date(Date.now() - h * 3_600_000).toISOString();

export const insight: ContactInsight = {
    summary:
        'Maria is an active buyer looking for a three-bedroom home in Katy, from Follow Up Boss, Lofty and BoldTrail. She toured two homes last week and asked about Katy ISD schools.',
    next_step: 'Text Maria two Katy listings near good schools',
    why: 'No touch in 12 days; she asked about Katy schools.',
    channel: 'text',
    draft: "Hi Maria, it's James. I found two Katy homes near the schools you asked about. Want to see them Saturday?",
    engine: 'ai',
    generated_at: hoursAgo(2),
};

export const maria: ContactRow = {
    id: '0193f0a2-7c1e-7000-8000-000000000001',
    name: 'Maria Lopez',
    first_name: 'Maria',
    last_name: 'Lopez',
    emails: ['maria.lopez@example.com'],
    phones: ['+18325550123'],
    stage: 'Active client',
    birthday: '1988-04-09',
    tags: ['katy-buyer'],
    sources: [
        {
            source: 'follow-up-boss',
            name: 'Follow Up Boss',
            synced_at: hoursAgo(1),
            campaign: null,
            campaign_status: null,
        },
        {
            source: 'lofty',
            name: 'Lofty',
            synced_at: hoursAgo(1),
            campaign: null,
            campaign_status: null,
        },
        {
            source: 'boldtrail',
            name: 'BoldTrail (kvCORE)',
            synced_at: hoursAgo(3),
            campaign: 'Buyer nurture',
            campaign_status: 'active',
        },
        {
            source: 'phone',
            name: 'Phone contacts',
            synced_at: hoursAgo(30),
            campaign: null,
            campaign_status: null,
        },
    ],
    updated_at: hoursAgo(1),
    segment: 'active',
    last_touch_at: hoursAgo(12 * 24),
    next_step: { text: insight.next_step, why: insight.why, channel: 'text' },
};

export const rows: ContactRow[] = [
    maria,
    {
        ...maria,
        id: '0193f0a2-7c1e-7000-8000-000000000002',
        name: 'David Chen',
        first_name: 'David',
        last_name: 'Chen',
        emails: ['dchen@example.com'],
        phones: ['+13465550142'],
        stage: 'Lead',
        tags: [],
        sources: [maria.sources[0], maria.sources[3]],
        segment: 'new',
        last_touch_at: null,
        next_step: null,
    },
];

export const panel: ContactPanelData = {
    contact: maria,
    address: {
        street: '2207 Bellaire Blvd',
        city: 'Houston',
        state: 'TX',
        postal_code: '77027',
    },
    notes: [
        {
            id: 1,
            body: 'Toured two homes in Cinco Ranch. Loved the second; wants a bigger yard.',
            source: 'follow-up-boss',
            source_name: 'Follow Up Boss',
            sent_to: [],
            at: hoursAgo(12 * 24),
        },
        {
            id: 2,
            body: 'Asked about Katy ISD schools.',
            source: 'reos',
            source_name: 'REOS',
            sent_to: ['Follow Up Boss', 'Lofty', 'BoldTrail (kvCORE)'],
            at: hoursAgo(13 * 24),
        },
    ],
    tasks: [
        {
            id: 7,
            title: 'Send Maria the Katy school ratings',
            due_at: null,
            due_date: '2027-01-08',
            done: false,
            source: 'crm',
        },
        {
            id: 8,
            title: 'Book second showing',
            due_at: null,
            due_date: null,
            done: true,
            source: 'typed',
        },
    ],
    activity: [
        {
            id: 3,
            kind: 'changed',
            source_name: 'Follow Up Boss',
            text: 'Follow Up Boss changed the stage of Maria Lopez to “Active client”.',
            occurred_at: hoursAgo(24),
        },
    ],
    insight,
    segment: 'active',
    last_touch_at: hoursAgo(12 * 24),
};
