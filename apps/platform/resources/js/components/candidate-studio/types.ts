/** The candidate studio (B13.3), as the page and routes send it. */
export type Milestone = 'exam_pass' | 'study_streak' | 'practice_score';

export type Moment = {
    milestone: Milestone;
    label: string;
    made: string | null;
};

export type MadePost = {
    id: string;
    milestone: Milestone;
    label: string;
    captions: { tone: 'professional' | 'warm' | 'short'; text: string }[];
    designs: { layout: string; path: string; url: string }[];
    checks: {
        caption: number;
        ok: boolean;
        issues: { code: string; phrase: string | null; citation: string }[];
    }[];
};

export type Target = {
    connection: number;
    channel: string;
    id: string;
    name: string;
};
