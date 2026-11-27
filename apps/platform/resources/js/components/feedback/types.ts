/** Feedback from agents (A13.3b), as the page and API send it. */
export type Dimension = 'communication' | 'responsiveness' | 'professionalism';

export type ToRate = {
    deal: string;
    property: string;
    closed_at: string | null;
    agent: { id: number; name: string };
};

export type FeedbackItem = {
    id: string;
    month: string;
    communication: number;
    responsiveness: number;
    professionalism: number;
    comment: string | null;
    response: string | null;
    reported: boolean;
};

export type MyFeedback = {
    count: number;
    needed: number;
    shown: boolean;
    average: {
        overall: number;
        communication: number;
        responsiveness: number;
        professionalism: number;
    } | null;
    items: FeedbackItem[];
    tip: {
        dimension: Dimension;
        text: string;
        engine: 'claude' | 'template';
    } | null;
    public: boolean;
};

export type FeedbackView = { to_rate: ToRate[]; mine: MyFeedback };

export type Rating = {
    communication: number;
    responsiveness: number;
    professionalism: number;
    comment: string;
};

export const DIMENSIONS: Dimension[] = [
    'communication',
    'responsiveness',
    'professionalism',
];
