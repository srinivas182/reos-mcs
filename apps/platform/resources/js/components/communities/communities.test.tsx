import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { CommunityPanel, JoinCard } from './community';
import {
    jamesView,
    mayaCommunity,
    mayaInvite,
    noRoutes,
    roster,
} from './fixtures';
import { RosterPanel, ScheduleForm } from './owner';

const api = vi.hoisted(() => ({
    getJson: vi.fn(),
    postJson: vi.fn(),
    putJson: vi.fn(),
    deleteJson: vi.fn(),
}));
vi.mock('@/lib/content', () => api);
const toast = vi.hoisted(() =>
    Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
);
vi.mock('sonner', () => ({ toast }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    ...noRoutes,
    post: '/posts',
    rsvp: (id: string) => `/rsvp/${id}`,
    attendance: (id: string) => `/att/${id}`,
    schedule: '/sessions',
};

describe('Communities (B17.2)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('posts, and shows why a post was turned down', async () => {
        const onChanged = vi.fn();
        api.postJson.mockRejectedValueOnce(
            new Error(
                'Communities can\'t be used to pay for referrals: remove "referral fee".',
            ),
        );
        render(
            <CommunityPanel
                community={jamesView}
                routes={routes}
                onChanged={onChanged}
            />,
        );

        await userEvent.type(
            screen.getByLabelText('Write a post'),
            'I pay a referral fee',
        );
        await userEvent.click(screen.getByRole('button', { name: 'Post' }));
        expect(
            await screen.findByText(
                'Communities can\'t be used to pay for referrals: remove "referral fee".',
            ),
        ).toBeInTheDocument();

        api.postJson.mockResolvedValueOnce({ data: { id: 'p3' } });
        await userEvent.clear(screen.getByLabelText('Write a post'));
        await userEvent.type(
            screen.getByLabelText('Write a post'),
            'What do buyers ask about escrow?',
        );
        await userEvent.click(screen.getByRole('button', { name: 'Post' }));
        expect(api.postJson).toHaveBeenLastCalledWith('/posts', {
            body: 'What do buyers ask about escrow?',
        });
        expect(onChanged).toHaveBeenCalled();
    });

    it('RSVPs, says what going means, and shows the CE note', async () => {
        api.postJson.mockResolvedValue({
            data: { status: 'going', checkout_url: null },
        });
        render(
            <CommunityPanel
                community={jamesView}
                routes={routes}
                onChanged={() => undefined}
            />,
        );

        expect(
            screen.getByRole('link', { name: 'Join online' }),
        ).toHaveAttribute('href', 'https://meet.example.com/maya');
        expect(
            screen.getByText(
                'Counts for CE as stated by the host (course TREC 41234).',
            ),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'RSVP and pay $25' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/rsvp/s2');
    });

    it('gives the owner the roster instead of RSVP, and saves attendance', async () => {
        const onRoster = vi.fn();
        const { unmount } = render(
            <CommunityPanel
                community={mayaCommunity}
                routes={routes}
                onChanged={() => undefined}
                onRoster={onRoster}
            />,
        );
        expect(screen.queryByRole('button', { name: 'RSVP' })).toBeNull();
        await userEvent.click(
            screen.getAllByRole('button', { name: "Who's going" })[0]!,
        );
        expect(onRoster).toHaveBeenCalledWith(mayaCommunity.sessions[0]);
        unmount();

        api.putJson.mockResolvedValue({ data: roster });
        render(
            <RosterPanel
                title="Reading a loan estimate"
                rows={roster}
                url="/att/s1"
                onSaved={() => undefined}
            />,
        );
        await userEvent.click(screen.getByLabelText('James Miller came'));
        await userEvent.click(
            screen.getByRole('button', { name: 'Save attendance' }),
        );
        expect(api.putJson).toHaveBeenCalledWith('/att/s1', { user_ids: [11] });
    });

    it('keeps the price off on Pro', () => {
        render(
            <ScheduleForm
                url="/sessions"
                canCharge={false}
                onScheduled={() => undefined}
            />,
        );
        expect(
            screen.getByLabelText('Price in dollars (0 for free)'),
        ).toBeDisabled();
        expect(
            screen.getByText('Paid sessions are for Partner providers.'),
        ).toBeInTheDocument();
    });

    it('joins from the invite, and explains when someone is not verified', async () => {
        api.postJson.mockResolvedValue({ data: { id: mayaInvite.id } });
        const { unmount } = render(
            <JoinCard
                invite={mayaInvite}
                joinUrl="/join"
                via="qr"
                openUrl="/c"
            />,
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Join community' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/join', { via: 'qr' });
        expect(
            await screen.findByRole('link', { name: 'Open community' }),
        ).toHaveAttribute('href', '/c');
        unmount();
        render(
            <JoinCard
                invite={{ ...mayaInvite, verified: false }}
                joinUrl="/join"
                via="link"
                openUrl="/c"
            />,
        );
        expect(
            screen.queryByRole('button', { name: 'Join community' }),
        ).toBeNull();
    });
});
