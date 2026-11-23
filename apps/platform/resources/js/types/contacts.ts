/** The CRM hub (B8.3, web screen 13): the shapes the server sends. */

export type Segment = 'all' | 'new' | 'active' | 'past' | 'sphere';

export type ContactSource = {
    source: string;
    name: string;
    synced_at: string | null;
    campaign: string | null;
    campaign_status: string | null;
};

export type ContactSummary = {
    id: string;
    name: string;
    first_name: string | null;
    last_name: string | null;
    emails: string[];
    phones: string[];
    stage: string | null;
    birthday: string | null;
    tags: string[];
    sources: ContactSource[];
    updated_at: string | null;
};

export type ContactRow = ContactSummary & {
    segment: Exclude<Segment, 'all'> | null;
    last_touch_at: string | null;
    next_step: { text: string; why: string; channel: string } | null;
};

export type ContactNote = {
    id: number;
    body: string;
    source: string;
    source_name: string;
    sent_to: string[];
    at: string | null;
};

export type ContactTask = {
    id: number;
    title: string;
    due_at: string | null;
    due_date: string | null;
    done: boolean;
    source: string;
};

export type ContactActivityLine = {
    id: number;
    kind: string;
    source_name: string;
    text: string;
    occurred_at: string;
};

export type ContactInsight = {
    summary: string;
    next_step: string;
    why: string;
    channel: 'call' | 'text' | 'email' | 'note';
    draft: string | null;
    engine: 'ai' | 'simulator';
    generated_at: string;
};

export type ContactPanelData = {
    contact: ContactSummary;
    address: Record<string, string> | null;
    notes: ContactNote[];
    tasks: ContactTask[];
    activity: ContactActivityLine[];
    insight: ContactInsight | null;
    segment: Exclude<Segment, 'all'> | null;
    last_touch_at: string | null;
};

/** A connected CRM and where new contacts go (screen 24, B8.3b). */
export type CrmOption = {
    connection: number;
    integration: string;
    name: string;
    account: string | null;
    takes_new_people: boolean;
    is_default: boolean;
};
