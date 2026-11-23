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
    messages: AskMessage[];
};

export type AskThreadRow = Omit<AskThread, 'messages'>;

export type AskPackage = {
    candidate: boolean;
    beyond_course: boolean;
    sponsor: string | null;
    credits_left: number | null;
};

export type AskMode = 'ask' | 'simpler' | 'spanish' | 'quiz';

/** "3:20" for a lesson second. */
export function clockOf(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);

    return `${m}:${String(s).padStart(2, '0')}`;
}
