import type { CatalogueItem, ToolConnection } from '@/types';

/** Realistic tools and connections for tests and stories (demo data only). */
export const tool = (
    overrides: Partial<CatalogueItem> = {},
): CatalogueItem => ({
    id: 'google-calendar',
    name: 'Google Calendar',
    purpose: 'Your calendar, both ways.',
    category: 'calendar_email',
    group: 'calendar',
    status: 'enabled',
    action: 'connect',
    interested: false,
    retires_on: '',
    connected: 0,
    accounts: 'user',
    consent: null,
    ...overrides,
});

export const calendarConnection = (
    overrides: Partial<ToolConnection> = {},
): ToolConnection => ({
    id: 7,
    integration: 'google-calendar',
    integration_name: 'Google Calendar',
    owner: 'user',
    account: 'james.miller@lonestarrealty.example',
    status: 'active',
    demo: true,
    scopes: [],
    history: '3m',
    last_synced_at: new Date(Date.now() - 4 * 60_000).toISOString(),
    problem: null,
    problem_since: null,
    connected_at: '2026-11-01T10:00:00Z',
    streams: [{ key: 'events', direction: 'both', records: 248 }],
    calendars: [
        {
            id: 'primary',
            name: 'james.miller@lonestarrealty.example',
            primary: true,
            writable: true,
            mode: 'full',
            write: true,
        },
        {
            id: 'personal',
            name: 'Personal',
            primary: false,
            writable: true,
            mode: 'busy',
            write: false,
        },
        {
            id: 'holidays',
            name: 'Holidays in United States',
            primary: false,
            writable: false,
            mode: 'off',
            write: false,
        },
    ],
    ...overrides,
});
