import type { AgentNetwork, ProviderConnection } from './types';

const chen = {
    id: 3,
    name: 'Chen Home Lending',
    category: 'Loan officer',
    photo_url: null,
};

/** James and Maya, connected; James shared, Maya hasn't yet. */
export const jamesShared: ProviderConnection = {
    id: '01936b7a-0000-7000-8000-000000000601',
    side: 'agent',
    with: 'Maya Chen',
    provider: chen,
    connected_at: '2026-11-29T10:00:00-06:00',
    i_shared: true,
    they_shared: false,
    contacts: null,
    conversation: '01936b7a-0000-7000-8000-000000000701',
    blocked: false,
};

/** Both shared: phone and email show. */
export const bothShared: ProviderConnection = {
    ...jamesShared,
    they_shared: true,
    contacts: { email: 'maya@chenlending.example', phone: '+1 713 555 0199' },
};

/** Maya's side: James shared, so Share shows his contacts too. */
export const mayaSide: ProviderConnection = {
    ...jamesShared,
    side: 'provider',
    with: 'James Miller',
    i_shared: false,
    they_shared: true,
};

export const jamesNetwork: AgentNetwork = {
    pending: [
        {
            id: '01936b7a-0000-7000-8000-000000000602',
            provider: {
                id: 4,
                name: 'Patel Inspections',
                category: 'Home inspector',
                photo_url: null,
            },
            message:
                'I inspect in Katy within 48 hours, with reports the same day.',
            sent_at: '2026-11-30T09:00:00-06:00',
            expires_at: '2026-12-14T09:00:00-06:00',
        },
    ],
    connections: [jamesShared],
    preferences: {
        all: true,
        categories: [
            { key: 'home-inspector', name: 'Home inspector', on: true },
            { key: 'loan-officer', name: 'Loan officer', on: true },
            { key: 'title-company', name: 'Title company', on: false },
        ],
    },
};

export const emptyNetwork: AgentNetwork = {
    pending: [],
    connections: [],
    preferences: { ...jamesNetwork.preferences },
};
