/** Agent modes (A11.1a): what `AgentModes::show()` sends. */
export type Goal = 'launch' | 'grow' | 'lead' | 'own';

export type Flag =
    | 'part_time'
    | 'bilingual'
    | 'specialist'
    | 'referral_only'
    | 'returning';

export const GOALS: Goal[] = ['launch', 'grow', 'lead', 'own'];

export const FLAGS: Flag[] = [
    'part_time',
    'bilingual',
    'specialist',
    'referral_only',
    'returning',
];

export type ModeState = {
    goal: Goal | null;
    flags: Flag[];
    needs_setup: boolean;
    suggested_default: Goal;
    own: { unlocked: boolean; unlocks_on: string | null; years: number };
    suggestion: { goal: Goal; closings: number } | null;
    history: {
        from: Goal | null;
        to: Goal;
        reason: 'setup' | 'manual' | 'suggested';
        at: string;
    }[];
    welcome: {
        name: string;
        licence: string | null;
        state: string | null;
        brokerage: string | null;
        verified: boolean;
    };
};
