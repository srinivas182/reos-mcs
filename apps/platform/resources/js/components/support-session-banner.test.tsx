import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import {
    minutesLeft,
    SupportSessionNotice,
} from '@/components/support-session-banner';
import { i18n } from '@/lib/i18n';
import SupportAccessShow from '@/pages/support-access/show';

const router = vi.hoisted(() => ({ delete: vi.fn(), post: vi.fn() }));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const grant = {
    id: 12,
    tenant: 'Lone Star Realty',
    requester: 'Ops Srini',
    reason: 'Linda asked us to check her calendar sync',
    status: 'pending' as const,
    requested_at: '2026-11-21T09:00:00Z',
    ends_at: null,
    decided_by: null,
    minutes: 60,
    answer_hours: 24,
};

describe('Support session banner (A4.1c)', () => {
    it('says where, how long is left and that pages are logged, with a way out', async () => {
        render(
            <SupportSessionNotice
                tenant="Lone Star Realty"
                endsAt={new Date(
                    Date.now() + 42 * 60_000 - 1_000,
                ).toISOString()}
            />,
        );

        expect(
            screen.getByText(
                "You're in Lone Star Realty as REOS support · 42 minutes left · every page is logged",
            ),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'End support session' }),
        );
        expect(router.delete).toHaveBeenCalledWith('/support-session');
    });

    it('counts whole minutes, never below zero', () => {
        const now = Date.parse('2026-11-21T10:00:00Z');
        expect(minutesLeft('2026-11-21T10:59:01Z', now)).toBe(60);
        expect(minutesLeft('2026-11-21T09:00:00Z', now)).toBe(0);
        expect(minutesLeft(null, now)).toBe(0);
    });
});

describe('Support sign-in request, for the owner (A4.1c)', () => {
    it('shows who asks, why and exactly what support can do, then Approve or Decline', async () => {
        render(<SupportAccessShow grant={grant} />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Support sign-in request',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('Ops Srini')).toBeInTheDocument();
        expect(
            screen.getByText('Linda asked us to check her calendar sync'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Works in Lone Star Realty only, as its admin, for 60 minutes at most.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                "Never sees anyone's personal connections, such as an agent's own calendar.",
            ),
        ).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Approve' }));
        expect(router.post).toHaveBeenCalledWith(
            '/support-access/12/approve',
            {},
            expect.anything(),
        );
    });

    it('needs two-step to approve, but never to decline', async () => {
        render(<SupportAccessShow grant={grant} twoStep={false} />);

        expect(screen.getByRole('button', { name: 'Approve' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Decline' })).toBeEnabled();
        expect(
            screen.getByText(/Approving needs two-step/),
        ).toBeInTheDocument();
    });

    it('lets the owner end access that is under way', async () => {
        render(
            <SupportAccessShow
                grant={{
                    ...grant,
                    status: 'active',
                    ends_at: '2026-11-21T10:30:00Z',
                    decided_by: 'Linda Park',
                }}
            />,
        );

        expect(screen.queryByRole('button', { name: 'Approve' })).toBeNull();
        await userEvent.click(
            screen.getByRole('button', { name: 'End support access' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/support-access/12/revoke',
            {},
            expect.anything(),
        );
    });
});
