/** The content studio's maker (B12.1), as the API and page send it. */
export type Output = 'post' | 'carousel' | 'ad';
export type SourceType = 'listing' | 'sale' | 'market' | 'text';
export type Tone = 'professional' | 'warm' | 'short';

export type Generation = {
    id: string;
    source: { type: SourceType; id: string | null; label: string | null };
    listing: string | null;
    outputs: Output[];
    captions: { tone: Tone; text: string }[];
    designs: { layout: 'band' | 'top' | 'split'; path: string; url: string }[];
    carousel: { path: string; url: string }[];
    ad: { headlines: string[]; primary: string[]; description: string } | null;
    credits: number;
    engine: string;
    created_at: string | null;
};

export type Maker = {
    cost: number;
    credits_left: number;
    listings: {
        id: string;
        address: string;
        status: string;
        photo: string | null;
    }[];
    sales: { id: string; property: string }[];
    generations: Generation[];
    video_cost: number;
    tracks: Track[];
    videos: Video[];
};

/** A studio video (B12.2), as the API and page send it. */
export type Track = 'calm' | 'bright' | 'none';

export type Video = {
    id: string;
    source: {
        type: 'listing' | 'own';
        id: string | null;
        label: string | null;
    };
    listing: string | null;
    title: string;
    scenes: {
        media: string;
        url: string;
        kind: 'image' | 'video';
        start: number;
        seconds: number;
        length: number | null;
        caption: string;
    }[];
    music: Track;
    brand: boolean;
    status: 'rendering' | 'ready' | 'failed';
    seconds: number | null;
    video: { path: string; url: string } | null;
    poster: { path: string; url: string } | null;
    error: string | null;
    issues: { code: string; phrase: string; fix: string }[];
    credits: number;
    engine: string;
    created_at: string | null;
    rendered_at: string | null;
};

export type MakeRequest = {
    source: {
        type: SourceType;
        id?: string | null;
        text?: string | null;
        city?: string | null;
    };
    outputs: Output[];
};
