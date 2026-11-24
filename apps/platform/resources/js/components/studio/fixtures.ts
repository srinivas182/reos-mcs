import type { Generation, Maker, Video } from './types';

/** Realistic REOS content for the studio's stories and tests. */
export const generation = (over: Partial<Generation> = {}): Generation => ({
    id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e55',
    source: {
        type: 'listing',
        id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e66',
        label: '2418 Willow Bend Dr, Katy, TX 77494',
    },
    listing: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e66',
    outputs: ['post', 'carousel', 'ad'],
    captions: [
        {
            tone: 'professional',
            text: 'Just listed: 2418 Willow Bend Dr, Katy. 4 bedrooms, 3 baths, offered at $389,000. Message me for details or a private showing. #JustListed #KatyRealEstate',
        },
        {
            tone: 'warm',
            text: 'Say hello to 2418 Willow Bend Dr! 4 bedrooms, 3 baths and room to make it your own, at $389,000. Want to see it? Send me a message. #JustListed #KatyHomes',
        },
        {
            tone: 'short',
            text: 'New on the market: 2418 Willow Bend Dr, $389,000. #JustListed',
        },
    ],
    designs: [
        {
            layout: 'band',
            path: 'social/1/studio/a-band.png',
            url: '/demo/listings/1.png',
        },
        {
            layout: 'top',
            path: 'social/1/studio/a-top.png',
            url: '/demo/listings/2.png',
        },
        {
            layout: 'split',
            path: 'social/1/studio/a-split.png',
            url: '/demo/listings/3.png',
        },
    ],
    carousel: [
        { path: 'social/1/studio/a-slide-1.png', url: '/demo/listings/4.png' },
        {
            path: 'social/1/studio/a-slide-card.png',
            url: '/demo/listings/5.png',
        },
    ],
    ad: {
        headlines: [
            'Just listed: 2418 Willow Bend Dr',
            'Offered at $389,000',
            'Book a private showing',
        ],
        primary: ['Just listed in Katy: 4 bedrooms, 3 baths.'],
        description: 'Message me today',
    },
    credits: 5,
    engine: 'simulator',
    created_at: '2026-11-24T15:00:00Z',
    ...over,
});

export const maker = (over: Partial<Maker> = {}): Maker => ({
    cost: 5,
    credits_left: 495,
    listings: [
        {
            id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e66',
            address: '2418 Willow Bend Dr, Katy, TX 77494',
            status: 'active',
            photo: '/demo/listings/1.svg',
        },
    ],
    sales: [
        {
            id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e77',
            property: '88 Oak Ridge Dr, Katy',
        },
    ],
    generations: [],
    video_cost: 30,
    tracks: ['calm', 'bright', 'none'],
    videos: [],
    ...over,
});

export const video = (over: Partial<Video> = {}): Video => ({
    id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e99',
    source: {
        type: 'listing',
        id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e66',
        label: '2418 Willow Bend Dr, Katy, TX 77494',
    },
    listing: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e66',
    title: 'Step inside this Katy home',
    scenes: [
        {
            media: '/demo/listings/1.svg',
            url: '/demo/listings/1.svg',
            kind: 'image',
            start: 0,
            seconds: 6,
            length: null,
            caption: '2418 Willow Bend Dr, Katy, TX 77494',
        },
    ],
    music: 'calm',
    brand: true,
    status: 'ready',
    seconds: 30,
    video: {
        path: 'social/1/studio/v-1-v1.mp4',
        url: '/demo/videos/listing.mp4',
    },
    poster: {
        path: 'social/1/studio/v-1-v1.jpg',
        url: '/demo/listings/1.png',
    },
    error: null,
    issues: [],
    credits: 30,
    engine: 'ffmpeg',
    created_at: '2026-11-24T15:00:00Z',
    rendered_at: '2026-11-24T15:01:00Z',
    ...over,
});
