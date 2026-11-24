/** CE reporting to TREC (A11.3): what `CeReporting::overview()` sends. */
export type ReadyRow = {
    completion: number;
    student: string;
    licence: string | null;
    course: string;
    course_number: string | null;
    hours: number;
    completed_on: string;
    problem: 'no_licence' | 'no_approval' | null;
    rejected: string | null;
};

export type ReportStatus = 'pending_upload' | 'submitted' | 'reported';
export type LineStatus = 'included' | 'reported' | 'rejected';

export type ReportSummary = {
    id: string;
    from_on: string;
    to_on: string;
    method: 'file' | 'reos';
    status: ReportStatus;
    reference: string | null;
    count: number;
    reported: number;
    rejected: number;
    created_by: string | null;
    created_at: string | null;
    lines: {
        completion: number;
        student: string;
        course: string;
        status: LineStatus;
        reason: string | null;
    }[];
};

export type Reporting = {
    ready: ReadyRow[];
    rejected: ReadyRow[];
    reports: ReportSummary[];
    reos: 'available' | 'simulated' | 'unavailable';
};
