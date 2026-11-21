import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { inviteOptions } from '@/components/invite-fixtures';
import { i18n } from '@/lib/i18n';
import Agents from '@/pages/organisation/agents';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    delete: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/agents', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const props = {
    tenant: { name: 'Lone Star Realty', team: null },
    agents: [
        {
            id: 1,
            name: 'James Miller',
            email: 'james@lonestar.example',
            team: null,
            last_active: new Date().toISOString(),
        },
        {
            id: 2,
            name: 'Rosa Lima',
            email: 'rosa@lonestar.example',
            team: 'Okoye Team',
            last_active: null,
        },
    ],
    invitations: [
        {
            id: 9,
            contact: 'tom@example.com',
            channel: 'email' as const,
            name: 'Tom Nguyen',
            role: 'agent' as const,
            team: 'Okoye Team',
            state: 'pending' as const,
            sent_at: '2026-11-20T10:00:00Z',
            expires_at: '2026-12-04T10:00:00Z',
            reminded: false,
            invited_by: 'Linda Park',
        },
    ],
    invite: inviteOptions(),
};

beforeEach(() => vi.clearAllMocks());

describe('Agents (screen 32)', () => {
    it('lists agents with team and last activity, seats and the privacy note', () => {
        render(<Agents {...props} />);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Agents' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('2 agents · Lone Star Realty'),
        ).toBeInTheDocument();
        const rosa = screen.getByRole('row', { name: /Rosa Lima/ });
        expect(within(rosa).getByText('Okoye Team')).toBeInTheDocument();
        expect(within(rosa).getByText('Not yet')).toBeInTheDocument();
        expect(
            screen.getByText(
                'Seats: 118 of 142 in use · 3 invited · Lone Star Realty pays',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/never shown to the brokerage/),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Export' })).toHaveAttribute(
            'href',
            '/agents/export',
        );
    });

    it('shows open invites with Resend and Withdraw', async () => {
        render(<Agents {...props} />);

        await userEvent.click(screen.getByRole('tab', { name: 'Invited 1' }));
        expect(screen.getByText('Waiting')).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Resend invite to tom@example.com',
            }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/organisation/invites/9/resend',
            {},
            expect.anything(),
        );
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Withdraw invite for tom@example.com',
            }),
        );
        expect(router.delete).toHaveBeenCalledWith(
            '/organisation/invites/9',
            expect.anything(),
        );
    });

    it('teaches what to do when there is nobody yet, and opens the invite sheet', async () => {
        render(<Agents {...props} agents={[]} invitations={[]} />);

        expect(screen.getByText('No agents yet')).toBeInTheDocument();
        await userEvent.click(
            screen.getAllByRole('button', { name: 'Invite agents' })[0],
        );
        expect(
            screen.getByRole('heading', { name: 'Invite people' }),
        ).toBeInTheDocument();
    });
});
