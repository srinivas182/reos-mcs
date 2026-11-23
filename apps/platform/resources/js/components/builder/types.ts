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
            return 'bg-reos-tone-info-bg text-reos-tone-info-fg';
        case 'rejected':
        case 'failed':
            return 'bg-reos-tone-danger-bg text-reos-tone-danger-fg';
        default:
            return 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg';
    }
}
