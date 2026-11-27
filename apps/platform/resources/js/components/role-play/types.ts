/** Role-play and the skills assessment (A14.1a), as the page and API send them. */
export type Dimension =
    | 'structure'
    | 'questions'
    | 'objections'
    | 'clarity'
    | 'ask';

export const DIMENSIONS: Dimension[] = [
    'structure',
    'questions',
    'objections',
    'clarity',
    'ask',
];

export type SkillType =
    | 'listing'
    | 'objection'
    | 'prospecting'
    | 'appraisal_gap'
    | 'follow_up';

export type Persona =
    | 'interviewing_seller'
    | 'downsizing_couple'
    | 'rate_worried_buyer'
    | 'commission_pushback'
    | 'fsbo'
    | 'expired'
    | 'low_appraisal'
    | 'cold_lead';

export type Lesson =
    | 'listing_presentation'
    | 'discovery_questions'
    | 'objection_handling'
    | 'clear_speaking'
    | 'asking_for_business';

export type Turn = { role: 'persona' | 'agent'; text: string };

export type Fix = {
    dimension: Dimension;
    text: string;
    example: string;
    lesson: Lesson;
};

export type Session = {
    id: string;
    type: SkillType;
    persona: Persona;
    kind: 'practice' | 'assessment';
    round: number | null;
    status: 'active' | 'scored';
    turns: Turn[];
    turns_left: number;
    score: number | null;
    scores: Record<Dimension, number> | null;
    fixes: Fix[];
    outcome: 'won' | 'lost' | null;
    engine: 'claude' | 'script';
    created_at: string;
};

export type Summary = {
    id: string;
    type: SkillType;
    persona: Persona;
    kind: 'practice' | 'assessment';
    status: 'active' | 'scored';
    score: number | null;
    created_at: string;
};

export type Assessment = {
    id: string;
    done: number;
    total: number;
    next: SkillType | null;
    completed: boolean;
    results: { type: SkillType; score: number }[];
    weakest: SkillType[];
    completed_at: string | null;
};

export type PracticeType = {
    type: SkillType;
    personas: Persona[];
    best: number | null;
};

export type Overview = {
    types: PracticeType[];
    recent: Summary[];
    assessment: Assessment | null;
};
