/** Coordinators (B17.1): a deal shared by part with a transaction coordinator, until closing. */
export type SharePart = 'dates' | 'documents' | 'parties' | 'messages';
export type Switches = Record<SharePart, boolean>;

export type ShareRow = {
    id: string;
    coordinator: string;
    switches: Switches;
    status: string;
    end_reason: string | null;
    ended_at: string | null;
};

export type DealCoordinators = {
    coordinators: { id: number; name: string; business: string }[];
    shares: ShareRow[];
};

export type FileCard = {
    id: string;
    agent: string;
    property: string;
    stage: string;
    shared_on: string;
    next: { label: string; due_on: string } | null;
    missing: number;
    progress: { done: number; total: number };
};

export type FilesBoardView = {
    counts: { active: number; due_this_week: number; missing: number };
    files: FileCard[];
};

export type FileDetail = {
    id: string;
    agent: string;
    property: string;
    city: string | null;
    stage: string;
    shared_on: string;
    switches: Switches;
    dates:
        | { id: string; label: string; due_on: string; done: boolean }[]
        | null;
    documents: { name: string; status: string; done: boolean }[] | null;
    parties: { name: string; role: string }[] | null;
    draft: string;
    conversation: string | null;
};
