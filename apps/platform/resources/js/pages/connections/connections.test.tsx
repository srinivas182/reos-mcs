import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { toast } from 'sonner';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { calendarConnection, tool } from '@/components/tool-fixtures';
import { i18n } from '@/lib/i18n';
import Connections from '@/pages/connections/index';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({
        url: '/connections',
        props: { region: { code: 'TX', name: 'Texas' } },
    }),
}));
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const tools = [
    tool({ connected: 1 }),
    tool({
        id: 'follow-up-boss',
        name: 'Follow Up Boss',
        category: 'crm',
        group: 'crm',
        status: 'coming_soon',
        action: 'notify',
        purpose: 'Contacts, notes and tasks, both ways.',
    }),
    tool({
        id: 'dotloop',
        name: 'dotloop',
        category: 'transactions',
        group: 'deals',
        purpose: 'Loops, documents and deadlines.',
    }),
];

beforeEach(() => vi.clearAllMocks());
afterEach(async () => {
    await i18n.changeLanguage('en');
});

describe('Connections page (web journey screen 19)', () => {
    it('has the title, purpose, Request a tool, and every tool', () => {
        render(
            <Connections tools={tools} connections={[calendarConnection()]} />,
        );

        expect(
            screen.getByRole('heading', { level: 1, name: 'Connections' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Tools available to agents in Texas · managed by REOS',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Request a tool' }),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('article')).toHaveLength(3);
        expect(
            screen.getByText(/Your brokerage can hide tools it doesn't allow/),
        ).toBeInTheDocument();
    });

    it('filters by tool type', async () => {
        render(<Connections tools={tools} connections={[]} />);

        const tabs = within(
            screen.getByRole('tablist', { name: 'Tool types' }),
        );
        expect(tabs.getAllByRole('tab').map((t) => t.textContent)).toEqual([
            'All',
            'CRM',
            'Transactions',
            'Calendar and email',
        ]);

        await userEvent.click(tabs.getByRole('tab', { name: 'CRM' }));
        expect(screen.getAllByRole('article')).toHaveLength(1);
        expect(
            screen.getByRole('heading', { name: 'Follow Up Boss' }),
        ).toBeInTheDocument();
    });

    it('counts Notify me with Undo', async () => {
        router.post.mockImplementationOnce(
            (
                _url: string,
                _data: unknown,
                options: { onSuccess: () => void },
            ) => options.onSuccess(),
        );
        render(<Connections tools={tools} connections={[]} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Notify me · Follow Up Boss' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/connections/tools/follow-up-boss/interest',
            {},
            expect.anything(),
        );
        expect(toast).toHaveBeenCalledWith(
            "Noted. We'll tell you when Follow Up Boss arrives.",
            expect.objectContaining({
                action: expect.objectContaining({ label: 'Undo' }),
            }),
        );
    });

    it('opens Manage with Sync now, how far back and Disconnect', async () => {
        render(
            <Connections tools={tools} connections={[calendarConnection()]} />,
        );

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Manage Google Calendar and Gmail',
            }),
        );
        const dialog = within(screen.getByRole('dialog'));
        expect(
            dialog.getByText(
                'Signed in as james.miller@lonestarrealty.example.',
            ),
        ).toBeInTheDocument();
        expect(dialog.getByText('248 items synced')).toBeInTheDocument();

        await userEvent.click(dialog.getByRole('button', { name: 'Sync now' }));
        expect(router.post).toHaveBeenCalledWith(
            '/connections/7/sync',
            {},
            expect.anything(),
        );

        await userEvent.click(
            dialog.getByRole('radio', { name: 'Last 12 months' }),
        );
        await userEvent.click(dialog.getByRole('button', { name: 'Save' }));
        expect(router.patch).toHaveBeenCalledWith(
            '/connections/7',
            { history: '12m' },
            expect.anything(),
        );

        await userEvent.click(
            dialog.getByRole('button', { name: 'Disconnect' }),
        );
        expect(
            screen.getByText(
                /REOS stops syncing and removes the Google Calendar and Gmail data/,
            ),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Disconnect' }),
        );
        expect(router.delete).toHaveBeenCalledWith(
            '/connections/7',
            expect.anything(),
        );
    });

    it('explains an empty catalogue and offers Request a tool', () => {
        render(<Connections tools={[]} connections={[]} />);

        expect(screen.getByText('No tools to show yet')).toBeInTheDocument();
        expect(
            screen.getAllByRole('button', { name: 'Request a tool' }),
        ).toHaveLength(2);
    });
});

describe('tool types and order', () => {
    it('puts meeting tools with the calendars, rare types under More, and connected tools first', async () => {
        const { webTab, byNextStep } = await import('@/lib/connection-tabs');

        expect(webTab('meetings')).toBe('calendar_email');
        expect(webTab('lookups')).toBe('more');
        expect(webTab('crm')).toBe('crm');
        expect(
            [
                tool({ id: 'a', action: 'notify' }),
                tool({ id: 'b', action: 'none' }),
                tool({ id: 'c', connected: 1, action: 'connect' }),
                tool({ id: 'd', action: 'connect' }),
            ]
                .toSorted(byNextStep)
                .map((t) => t.id),
        ).toEqual(['c', 'd', 'a', 'b']);
    });
});
