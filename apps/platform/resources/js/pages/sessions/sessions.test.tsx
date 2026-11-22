import {
    act,
    render as baseRender,
    screen,
    within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { contacts, session } from '@/components/sessions/fixtures';
import { i18n } from '@/lib/i18n';
import SessionPage from '@/pages/sessions/show';
import Sessions from '@/pages/sessions/index';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));
const postJson = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: string;
        children: React.ReactNode;
    }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router,
    usePage: () => ({ url: '/sessions', props: { auth: { user: { id: 7 } } } }),
}));
vi.mock('@/lib/content', () => ({ postJson }));

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

describe('Sessions (web, B6.3)', () => {
    beforeEach(() => {
        router.post.mockReset();
        router.put.mockReset();
        postJson.mockReset();
    });

    afterEach(() => vi.useRealTimers());

    it('lists sessions by day, with Join once the room opens', () => {
        const soon = new Date(Date.now() + 60_000);
        render(
            <Sessions
                sessions={[
                    session({
                        can_join: true,
                        starts_at: soon.toISOString(),
                        ends_at: new Date(
                            soon.getTime() + 45 * 60_000,
                        ).toISOString(),
                    }),
                    session({
                        id: 's-0',
                        title: 'Getting started with the practice exams',
                        status: 'ended',
                        starts_at: '2026-11-17T22:00:00Z',
                        ends_at: '2026-11-17T22:30:00Z',
                    }),
                ]}
                canHost
                contacts={contacts}
                lengths={[30, 45, 60]}
            />,
        );
        expect(screen.getByRole('link', { name: 'Join' })).toHaveAttribute(
            'href',
            '/sessions/s-1',
        );
        expect(
            screen.getByRole('heading', { name: 'Past sessions' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: 'View: Getting started with the practice exams',
            }),
        ).toBeInTheDocument();
    });

    it('schedules a 1-on-1 with someone you work with', async () => {
        render(
            <Sessions
                sessions={[]}
                canHost
                contacts={contacts}
                lengths={[30, 45, 60]}
            />,
        );
        await userEvent.click(
            screen.getAllByRole('button', { name: 'Schedule a session' })[0],
        );
        const dialog = screen.getByRole('dialog', {
            name: 'Schedule a session',
        });
        await userEvent.type(
            within(dialog).getByLabelText('Title'),
            'Contract review',
        );
        await userEvent.click(within(dialog).getByLabelText(/Sofia Reyes/));
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Schedule' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/sessions',
            expect.objectContaining({
                kind: 'one_on_one',
                title: 'Contract review',
                minutes: 45,
                guests: [2],
            }),
            expect.anything(),
        );
    });

    it('joins the room, shows the timer and lets the host end it for everyone', async () => {
        postJson.mockResolvedValue({
            simulated: true,
            url: null,
            token: null,
            ends_at: new Date(Date.now() + 100_000).toISOString(),
        });
        render(
            <SessionPage
                session={session({ can_join: true, status: 'live' })}
                simulated
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Join' }));
        expect(postJson).toHaveBeenCalledWith('/sessions/s-1/join');
        expect(await screen.findByRole('timer')).toBeInTheDocument();
        // Under two minutes left: the warning.
        expect(
            screen.getByText(/Two minutes left\. The room closes for everyone/),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Dr. Rachel Hughes (you) · Host'),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'End for everyone' }),
        );
        expect(router.post).toHaveBeenCalledWith('/sessions/s-1/end');
    });

    it('closes the room at the booked end', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        postJson.mockResolvedValue({
            simulated: true,
            url: null,
            token: null,
            ends_at: new Date(Date.now() + 3_000).toISOString(),
        });
        render(
            <SessionPage
                session={session({ can_join: true, me: 'guest' })}
                simulated
            />,
        );
        await act(async () => {
            screen.getByRole('button', { name: 'Join' }).click();
        });
        expect(await screen.findByRole('timer')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'End for everyone' }),
        ).not.toBeInTheDocument();

        await act(async () => {
            vi.advanceTimersByTime(4_000);
        });
        expect(screen.queryByRole('timer')).not.toBeInTheDocument();
        expect(router.reload).toHaveBeenCalledWith({ only: ['session'] });
    });

    it('saves the shared notes', async () => {
        render(
            <SessionPage session={session({ status: 'ended' })} simulated />,
        );
        expect(
            screen.queryByRole('button', { name: 'Join' }),
        ).not.toBeInTheDocument();
        await userEvent.type(
            screen.getByRole('textbox', { name: 'Session notes' }),
            'Read section 4.',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Save notes' }),
        );
        expect(router.put).toHaveBeenCalledWith(
            '/sessions/s-1/notes',
            { notes: 'Read section 4.' },
            expect.anything(),
        );
    });
});
