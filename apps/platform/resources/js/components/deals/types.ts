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
    /** B9.3: PDFs kept in REOS (uploads, signed copies, certificates). */
    kept_files: KeptFile[];
    signatures: SignatureEnvelope[];
    /** The agent's DocuSign is connected. */
    can_sign: boolean;
};

export type KeptFile = {
    uuid: string;
    kind: 'upload' | 'signed' | 'certificate';
    name: string;
    size: number;
    added_at: string | null;
    /** Virus scan: only clean files open or are sent. */
    scan: 'pending' | 'clean' | 'infected';
    url: string | null;
};

export type SignerStatus =
    | 'waiting'
    | 'sent'
    | 'delivered'
    | 'signed'
    | 'declined';

export type SignatureEnvelope = {
    uuid: string;
    subject: string;
    status:
        | 'draft'
        | 'sent'
        | 'delivered'
        | 'completed'
        | 'declined'
        | 'voided';
    documents: string[];
    signers: {
        name: string;
        email: string;
        routing_order: number;
        status: SignerStatus;
        at: string | null;
    }[];
    sent_at: string | null;
    completed_at: string | null;
    filed_in: string | null;
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
