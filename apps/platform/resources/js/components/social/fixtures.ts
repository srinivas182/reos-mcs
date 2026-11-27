import type {
    CheckResult,
    Draft,
    PostList,
    SocialAccount,
    SocialPost,
} from './types';

/** James Miller's accounts: Meta connected (a Page and Instagram), LinkedIn coming. */
export function accounts(): SocialAccount[] {
    return [
        {
            integration: 'meta',
            name: 'Instagram and Facebook',
            purpose: 'Post, schedule and read insights.',
            launch: 'enabled',
            built: true,
            available: true,
            connection: { id: 4, status: 'active', label: 'James Miller' },
            targets: [
                {
                    channel: 'facebook',
                    id: 'p1',
                    name: 'James Miller, REALTOR®',
                },
                { channel: 'instagram', id: 'ig1', name: '@jamesmiller.homes' },
            ],
            channels: {
                instagram: { media: 'required', max_text: 2200 },
                facebook: { media: 'optional', max_text: 63206 },
            },
        },
        {
            integration: 'linkedin',
            name: 'LinkedIn',
            purpose: 'Post to profiles and company pages; insights.',
            launch: 'beta',
            built: false,
            available: false,
            connection: null,
            targets: [],
            channels: {},
        },
    ];
}

export function post(overrides: Partial<SocialPost> = {}): SocialPost {
    return {
        id: '0199a1b2-0000-7000-8000-000000000001',
        body: 'Just listed: 1420 Pecan St, Austin. 3 bed, 2 bath, big backyard. Open house Saturday 1–3 pm.',
        media: [{ url: '/demo/listings/pecan-1.svg', kind: 'image' }],
        link: 'https://reos.com/agent/james-miller',
        attribution: 'James Miller · Lone Star Realty',
        listing: null,
        status: 'published',
        scheduled_at: null,
        published_at: '2026-11-20T16:00:00Z',
        created_at: '2026-11-20T15:55:00Z',
        targets: [
            {
                id: 1,
                integration: 'meta',
                channel: 'facebook',
                target_id: 'p1',
                name: 'James Miller, REALTOR®',
                status: 'published',
                error: null,
                url: 'https://www.facebook.com/p1_1',
                published_at: '2026-11-20T16:00:00Z',
                insights: { reach: 1240, likes: 103, comments: 20, clicks: 49 },
            },
            {
                id: 2,
                integration: 'meta',
                channel: 'instagram',
                target_id: 'ig1',
                name: '@jamesmiller.homes',
                status: 'published',
                error: null,
                url: null,
                published_at: '2026-11-20T16:00:00Z',
                insights: {
                    reach: 2210,
                    likes: 184,
                    comments: 36,
                    views: 3094,
                },
            },
        ],
        approval_note: null,
        totals: {
            reach: 3450,
            likes: 287,
            comments: 56,
            clicks: 49,
            views: 3094,
        },
        ...overrides,
    };
}

export function postList(): PostList {
    return {
        stats: {
            posts_this_month: 4,
            reach: 6120,
            engagement: 512,
            clicks: 88,
        },
        scheduled: [
            post({
                id: '0199a1b2-0000-7000-8000-000000000002',
                status: 'scheduled',
                body: 'Open house this Saturday, 1–3 pm at 88 Oak Hollow Dr.',
                published_at: null,
                scheduled_at: '2026-11-28T15:00:00Z',
                targets: post().targets.map((t) => ({
                    ...t,
                    status: 'queued' as const,
                    insights: {},
                    url: null,
                })),
                approval_note: null,
                totals: {},
            }),
        ],
        posted: [
            post(),
            post({
                id: '0199a1b2-0000-7000-8000-000000000003',
                status: 'partly',
                targets: [
                    post().targets[0]!,
                    {
                        ...post().targets[1]!,
                        status: 'failed',
                        error: 'reconnect',
                        insights: {},
                    },
                ],
            }),
        ],
        drafts: [],
    };
}

export function check(overrides: Partial<CheckResult> = {}): CheckResult {
    return {
        attribution: 'James Miller · Lone Star Realty',
        checks: [
            { code: 'broker', ok: true, detail: null },
            { code: 'targets', ok: true, detail: null },
            { code: 'length', ok: true, detail: 'instagram' },
            { code: 'media', ok: true, detail: 'instagram' },
            { code: 'title', ok: true, detail: null },
        ],
        ok: true,
        approval: false,
        review: [],
        issues: [],
        ...overrides,
    };
}

export function draft(overrides: Partial<Draft> = {}): Draft {
    return {
        body: 'Market update: Austin prices held steady in November.',
        link: '',
        media: [],
        picks: [{ connection: 4, channel: 'facebook', id: 'p1' }],
        when: 'now',
        at: '',
        ...overrides,
    };
}
