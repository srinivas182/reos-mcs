/** Ask, the study mate (A10.1, candidate screen 13). */
export type AskSource = {
    lesson_id: number;
    lesson: string;
    seconds: number | null;
    page: number | null;
    heading: string | null;
    text: string;
};

export type AskMessage = {
    id: number;
    role: 'question' | 'answer' | 'instructor';
    body: string;
    tier: 'content' | 'sponsored' | 'limited' | null;
    mode: string | null;
    sources: AskSource[];
    quiz: {
        prompt: string;
        options: string[];
        correct: number;
        explanation: string;
    } | null;
    math_kind: string | null;
    sponsor: string | null;
    created_at: string | null;
};

export type AskThread = {
    uuid: string;
    title: string;
    updated_at: string | null;
    /** A10.1b: the last question went to the instructor (waiting) or they answered. */
    instructor: 'waiting' | 'answered' | null;
    messages: AskMessage[];
};

export type AskThreadRow = Omit<AskThread, 'messages' | 'instructor'>;

/** Screen k10: course answers are free; beyond the course, free monthly credits, then the sponsor, then credits bought. */
export type AskPackage = {
    candidate: boolean;
    beyond_course: boolean;
    free_left: number | null;
    free_monthly: number | null;
    sponsor: string | null;
    credits_left: number;
    cost: number;
};

export type AskMode = 'ask' | 'simpler' | 'spanish' | 'quiz';

/** "3:20" for a lesson second. */
export function clockOf(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);

    return `${m}:${String(s).padStart(2, '0')}`;
}

/** A student's question in the instructor's queue (A10.1b, screens i09 and i10). */
export type StudentQuestion = {
    uuid: string;
    student: string;
    course: string;
    question: string;
    content_answer: string | null;
    language: string;
    status: 'waiting' | 'answered';
    answer: string | null;
    answered_by: string | null;
    answered_at: string | null;
    added_to_knowledge: boolean;
    flashcard: boolean;
    asked_at: string | null;
};
