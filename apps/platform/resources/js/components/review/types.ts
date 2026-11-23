import type { LegalRule } from '@/components/builder/types';

export type ReviewLine = {
    n: number;
    en: string;
    es: string;
    source: string;
    page: number | null;
    status: 'to_review' | 'approved' | 'changed';
    rerecord: boolean;
    suggestion: { en: string; es: string } | null;
    comments: { by: number; name: string; body: string; at: string }[];
    flags: { rule: LegalRule; language: 'en' | 'es'; source: string | null }[];
};

export type ReviewItemRow = {
    id: number;
    kind: 'checkin' | 'flashcard';
    after_line: number | null;
    status: 'to_review' | 'approved' | 'rejected';
    content: {
        en?: { prompt?: string; front?: string; back?: string };
        es?: { prompt?: string; front?: string; back?: string };
    };
};

export type ReviewVideo = {
    language: 'en' | 'es';
    status: string;
    simulated: boolean;
    url: string | null;
};

export type ReviewVersion = {
    id: number;
    version: number;
    status: string;
    review_state: 'in_review' | 'sent_back' | 'approved' | null;
    review_note: string | null;
    vendor: string;
    credits: number;
};

export type AiLessonRow = {
    id: number;
    title: string;
    course: string;
    status: 'draft' | 'generating' | 'in_review' | 'approved';
    version: number | null;
    flags: number;
    blockers: number;
    updated_at: string | null;
};

export type QuestionLanguage = {
    prompt: string;
    options: string[];
    explanation: string;
};

export type QuestionRow = {
    id: number;
    course: string;
    lesson: string;
    topic: string | null;
    difficulty: 'easy' | 'medium' | 'hard' | null;
    portion: 'national' | 'state' | null;
    status: 'to_review' | 'approved' | 'rejected';
    source: string;
    en: QuestionLanguage | null;
    es: QuestionLanguage | null;
    correct: number;
};

export type DisputeRow = {
    id: number;
    reason: 'wrong_answer' | 'unclear' | 'out_of_date' | 'other';
    details: string | null;
    by: string;
    at: string | null;
    question: {
        id: number;
        text: string;
        options: string[];
        correct: number;
        source: string;
        review_item_id: number | null;
    };
};
