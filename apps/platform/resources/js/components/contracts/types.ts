/** Contract review's shapes (A12.2), as the API and pages send them. */
export type Finding = {
    code: string;
    severity: 'high' | 'check';
    title: string;
    detail: string;
    page: number | null;
    date: string | null;
    citation: string | null;
    url: string | null;
};

export type Deadline = {
    code: string;
    kind: string;
    label: string;
    due_on: string;
    page: number | null;
    basis: string;
};

export type TermRow = {
    code: string;
    label: string;
    value: string;
    page: number | null;
    previous: string | null;
    changed: boolean;
};

export type ContractQuestion = {
    question: string;
    answer: string;
    pages: number[];
    note: string;
    asked_at: string;
};

export type Addendum =
    | 'third_party_financing'
    | 'hoa'
    | 'sale_of_other_property'
    | 'seller_financing'
    | 'lead_paint';

export type ContractReview = {
    id: string;
    file_name: string;
    form: string | null;
    form_name: string | null;
    status: 'reviewing' | 'done' | 'failed';
    step: 'read' | 'terms' | 'checks' | 'compare' | 'done';
    pages: number;
    created_at: string | null;
    reviewed_at: string | null;
    deal: { id: number; uuid: string; property: string } | null;
    compared: boolean;
    terms: TermRow[];
    addenda: string[];
    findings: Finding[];
    deadlines: Deadline[];
    deadlines_added: boolean;
    broker_question: string | null;
    questions: ContractQuestion[];
    credits: number;
    cost: number;
    error: string | null;
};

export type ContractsOverview = {
    reviews: ContractReview[];
    cost: number;
    credits_left: number;
    sample: boolean;
    files: {
        uuid: string;
        name: string;
        deal: string;
        added_at: string | null;
    }[];
};
