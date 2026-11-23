/** Deals (B9.1, ADR 0037): the web Deals board (screen 14). */
export type DealStage = 'active' | 'under_contract' | 'closed' | 'cancelled';
export type DealSide = 'buy' | 'sell' | 'lease' | 'both';
export type DateKind =
    | 'option_period'
    | 'financing'
    | 'appraisal'
    | 'inspection'
    | 'survey'
    | 'title_commitment'
    | 'closing'
    | 'other';

export type DealDate = {
    id: number;
    kind: DateKind;
    name: string;
    due_on: string;
    days: number;
    done: boolean;
};

export type DealCard = {
    id: number;
    uuid: string;
    property: string;
    city: string | null;
    side: DealSide;
    stage: DealStage;
    price: number | null;
    commission: string | null;
    source: string;
    read_only: boolean;
    external_url: string | null;
    next: DealDate | null;
    documents: { complete: number; total: number; missing: number };
};

export type DealFull = DealCard & {
    parties: {
        name: string;
        role: string;
        email: string | null;
        phone: string | null;
    }[];
    files: {
        name: string;
        status: 'complete' | 'missing' | 'pending';
        url: string | null;
    }[];
    dates: DealDate[];
};

export type DealBoard = Record<
    'active' | 'under_contract' | 'closed',
    DealCard[]
>;

/** The tool's name for "From Lone Wolf" and "Open in Lone Wolf". */
export const TOOL_NAMES: Record<string, string> = {
    'lone-wolf': 'Lone Wolf',
    dotloop: 'dotloop',
    skyslope: 'SkySlope',
};
