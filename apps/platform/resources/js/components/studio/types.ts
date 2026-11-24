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
