import type { InviteOptions } from '@/types';

/** Lone Star Realty's invite options, for tests and stories. */
export const inviteOptions = (
    overrides: Partial<InviteOptions> = {},
): InviteOptions => ({
    roles: [
        { value: 'agent', label: 'Licensed agent', seat: true },
        { value: 'mentor', label: 'Mentor', seat: false },
        { value: 'broker', label: 'Broker', seat: false },
    ],
    teams: [{ id: 6, name: 'Okoye Team' }],
    can_text: true,
    seats: {
        role: 'agent',
        limit: 142,
        used: 118,
        pending: 3,
        left: 21,
        payer: 'organisation',
    },
    locale: 'en',
    ...overrides,
});
