import type { Checkpoint, PlayerState } from '@/lib/seat-time';

export const counting: PlayerState = {
    counted: 4320,
    required: 7200,
    percent: 60,
    reason: null,
    checkin: null,
    completed: false,
    today_left: null,
};

export const paused: PlayerState = { ...counting, reason: 'paused' };

export const nearLimit: PlayerState = {
    ...counting,
    reason: null,
    today_left: 900,
};

export const done: PlayerState = {
    ...counting,
    counted: 7260,
    percent: 100,
    completed: true,
};

export const question: Checkpoint = {
    key: 'checkin:12',
    at: 900,
    kind: 'question',
    question: {
        id: 41,
        prompt: 'In Texas, when must a license holder give the Information About Brokerage Services notice?',
        options: [
            'At the first substantive communication',
            'When the buyer signs a contract',
            'At closing',
            'Only if the buyer asks',
        ],
    },
};

export const presence: Checkpoint = {
    key: 'presence:1',
    at: 900,
    kind: 'presence',
    question: null,
};
