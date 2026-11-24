/** Social posting (B11): what `SocialAccounts` and `SocialPosts` send. */
export type Channel =
    | 'facebook'
    | 'instagram'
    | 'linkedin'
    | 'google_business'
    | 'youtube'
    | 'tiktok';

export type Target = { channel: Channel; id: string; name: string };

export type SocialAccount = {
    integration: string;
    name: string;
    purpose: string;
    launch: string;
    built: boolean;
    available: boolean;
    connection: { id: number; status: string; label: string | null } | null;
    targets: Target[];
    channels: Partial<
        Record<
            Channel,
            { media: 'required' | 'optional' | 'video'; max_text: number }
        >
    >;
};

export type PostStatus =
    | 'draft'
    | 'scheduled'
    | 'publishing'
    | 'published'
    | 'partly'
    | 'failed';
export type TargetStatus = 'queued' | 'publishing' | 'published' | 'failed';
export type Metric =
    | 'reach'
    | 'views'
    | 'likes'
    | 'comments'
    | 'clicks'
    | 'shares';

export type PostTarget = {
    id: number;
    integration: string;
    channel: Channel;
    target_id: string;
    name: string;
    status: TargetStatus;
    error: string | null;
    url: string | null;
    published_at: string | null;
    insights: Partial<Record<Metric, number>>;
};

export type SocialPost = {
    id: string;
    body: string;
    media: { url: string; kind: string }[];
    link: string | null;
    attribution: string;
    listing: string | null;
    status: PostStatus;
    scheduled_at: string | null;
    published_at: string | null;
    created_at: string | null;
    targets: PostTarget[];
    totals: Partial<Record<Metric, number>>;
};

export type PostList = {
    stats: {
        posts_this_month: number;
        reach: number;
        engagement: number;
        clicks: number;
    };
    scheduled: SocialPost[];
    posted: SocialPost[];
    drafts: SocialPost[];
};

export type CheckCode =
    | 'broker'
    | 'targets'
    | 'length'
    | 'media'
    | 'video'
    | 'title';

export type CheckResult = {
    attribution: string;
    checks: { code: CheckCode; ok: boolean; detail: Channel | null }[];
    ok: boolean;
};

export type Pick = { connection: number; channel: Channel; id: string };

/** A listing post to start from (B10.2): its caption, the photos the MLS allows and its attribution. */
export type ListingDraft = {
    listing: string;
    address: string;
    photos: { url: string; caption: string | null }[];
    max_photos: number;
    caption: string;
    chosen: string[];
    attribution: string;
    saved_at: string | null;
    sample: boolean;
};

export type Draft = {
    body: string;
    link: string;
    /** Uploaded paths, or the listing's photo URLs. */
    media: { value: string; preview: string; kind: string }[];
    picks: Pick[];
    when: 'now' | 'later' | 'draft';
    at: string;
};

export const METRICS: Metric[] = [
    'reach',
    'views',
    'likes',
    'comments',
    'clicks',
    'shares',
];
