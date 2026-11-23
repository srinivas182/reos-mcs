import type { ModeState } from './types';

/** James Miller, licensed in 2023, Launch mode with his first verified closing. */
export function modeState(overrides: Partial<ModeState> = {}): ModeState {
    return {
        goal: 'launch',
        flags: ['bilingual'],
        needs_setup: false,
        suggested_default: 'launch',
        own: { unlocked: false, unlocks_on: '2027-04-01', years: 4 },
        suggestion: null,
        history: [
            {
                from: null,
                to: 'launch',
                reason: 'setup',
                at: '2026-11-20T15:00:00Z',
            },
        ],
        welcome: {
            name: 'James Miller',
            licence: '0798765',
            state: 'TX',
            brokerage: 'Linda Park',
            verified: true,
        },
        ...overrides,
    };
}
