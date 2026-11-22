import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import Announcements from '@/pages/announcements/index';

const router = vi.hoisted(() => ({ post: vi.fn(), on: vi.fn(() => () => {}) }));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/announcements', props: { errors: {} } }),
}));

vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const props = {
    organisation: 'Lone Star Realty',
    sent: [
        {
            id: 'a-1',
            title: 'Office closed Friday',
            body: 'Showings still run.',
            author: 'Linda Park',
            audience: { roles: ['agent'] },
            recipients: 140,
            read: 82,
            sent_at: '2026-11-22T10:00:00Z',
        },
    ],
    options: {
        roles: ['agent', 'mentor'],
        teams: [{ id: 9, name: 'Okoye Team' }],
        states: ['TX'],
    },
};

describe('Announcements (B6.1b)', () => {
    it('shows what was sent with "Read by 82 of 140"', () => {
        render(<Announcements {...props} />);
        expect(
            screen.getByRole('heading', {
                level: 2,
                name: 'Office closed Friday',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('Read by 82 of 140')).toBeInTheDocument();
        expect(screen.getByText('To: Licensed agent')).toBeInTheDocument();
    });

    it('sends a new announcement to a team', async () => {
        render(<Announcements {...props} />);
        await userEvent.click(
            screen.getAllByRole('button', { name: 'New announcement' })[0],
        );
        const dialog = screen.getByRole('dialog', { name: 'New announcement' });
        await userEvent.type(
            within(dialog).getByLabelText('Title'),
            'Team huddle',
        );
        await userEvent.type(
            within(dialog).getByLabelText('Announcement'),
            'Friday at 9:30.',
        );
        await userEvent.click(within(dialog).getByLabelText('Okoye Team'));
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Send announcement' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/announcements',
            {
                title: 'Team huddle',
                body: 'Friday at 9:30.',
                roles: [],
                teams: [9],
                states: [],
            },
            expect.anything(),
        );
    });
});
