export type SourceRole =
    | 'script'
    | 'slides'
    | 'form'
    | 'recording'
    | 'captions';
export type LessonStatus = 'draft' | 'generating' | 'in_review' | 'approved';
export type ModuleStatus =
    | 'empty'
    | 'sources'
    | 'reading'
    | 'draft'
    | 'generating'
    | 'in_review'
    | 'approved';

export type BuilderCourse = {
    id: number;
    title: string;
    type: string;
    delivery: string;
    status: 'draft' | 'in_review' | 'live' | 'hidden';
    hours: number | null;
    hours_locked: boolean;
    approval: string | null;
    can_publish: boolean;
};

export type ModuleRow = {
    id: number;
    title: string;
    sources: number;
    lessons: number;
    approved: number;
    status: ModuleStatus;
};

export type SourceRow = {
    id: number;
    role: SourceRole;
    name: string;
    size: number;
    status: 'awaiting_upload' | 'processing' | 'ready' | 'rejected' | 'failed';
    failure: string | null;
    pages: number | null;
    words: number | null;
};

export type LessonRow = {
    id: number;
    title: string;
    minutes: number | null;
    language: string;
    status: LessonStatus;
    has_body: boolean;
    body: string | null;
    ai: LessonAi | null;
};

/** The legal-line rules (A5.3b) a script line can break. */
export type LegalRule =
    | 'guarantee'
    | 'pass_rate'
    | 'risk_free'
    | 'approval_claim'
    | 'superlative';

export type VersionStatus =
    | 'drafting'
    | 'flagged'
    | 'rendering'
    | 'ready'
    | 'failed';

/** An AI lesson's latest version in the lesson list (A7.3). */
export type LessonAi = {
    version: number;
    status: VersionStatus;
    flags: number;
};

/** An AI lesson's draft (A7.3): the script line by line in both languages, its sources, flags and videos. */
export type LessonDraft = {
    lesson: { id: number; title: string };
    version: number;
    versions: number;
    status: VersionStatus;
    failure: string | null;
    presenter: string;
    vendor: 'heygen' | 'synthesia';
    credits: number;
    lines: {
        n: number;
        en: string;
        es: string;
        source: string;
        page: number | null;
    }[];
    flags: { line: number; rule: LegalRule; language: 'en' | 'es' }[];
    renders: {
        language: 'en' | 'es';
        status: 'queued' | 'rendering' | 'ready' | 'failed';
        simulated: boolean;
        seconds: number | null;
        failure: string | null;
        url: string | null;
    }[];
    review: { checkins: number; questions: number; flashcards: number };
};

export type Plan = {
    words: number;
    minutes: number;
    lessons: number;
    checkins: number;
    questions: number;
    flashcards: number;
    credits: number;
    sources_ready: number;
    sources_waiting: number;
    credits_left: number;
};

export type BuilderModule = {
    id: number;
    title: string;
    sources: SourceRow[];
    lessons: LessonRow[];
    plan: Plan;
};

export type BuilderSettings = {
    language: 'both' | 'en' | 'es';
    lesson_minutes: number;
    presenter: string;
    vendor: 'heygen' | 'synthesia';
};

export type RoleRule = { role: SourceRole; types: string[]; max_mb: number };

/** Chip tone for a lesson or module status. */
export function statusTone(status: string): string {
    switch (status) {
        case 'approved':
        case 'live':
        case 'ready':
            return 'bg-reos-tone-success-bg text-reos-tone-success-fg';
        case 'in_review':
        case 'generating':
        case 'reading':
        case 'processing':
        case 'drafting':
        case 'rendering':
        case 'queued':
            return 'bg-reos-tone-info-bg text-reos-tone-info-fg';
        case 'rejected':
        case 'failed':
        case 'flagged':
            return 'bg-reos-tone-danger-bg text-reos-tone-danger-fg';
        default:
            return 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg';
    }
}
