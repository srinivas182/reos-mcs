import type { CatalogueItem, ToolConnection } from '@/types';

/** Realistic tools and connections for tests and stories (demo data only). */
export const tool = (
    overrides: Partial<CatalogueItem> = {},
): CatalogueItem => ({
    id: 'google-workspace',
    name: 'Google Calendar and Gmail',
    purpose: 'Your calendar and email, both ways.',
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
    integration: 'google-workspace',
    integration_name: 'Google Calendar and Gmail',
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
    streams: [{ key: 'events', direction: 'read', records: 248 }],
    ...overrides,
});
