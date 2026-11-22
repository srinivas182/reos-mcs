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
// Excalidraw needs a real canvas; the room's switching and sharing are what's tested here.
vi.mock('@/components/sessions/whiteboard', () => ({
    default: ({ sessionId }: { sessionId: string }) => (
        <div role="application" aria-label="Shared whiteboard">
            {sessionId}
        </div>
    ),
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

    it('switches to the whiteboard and lets the host share practice questions', async () => {
        postJson.mockResolvedValueOnce({
            simulated: true,
            url: null,
            token: null,
            ends_at: new Date(Date.now() + 30 * 60_000).toISOString(),
        });
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve(
                    new Response(
                        JSON.stringify({
                            lessons: [],
                            topics: [{ topic: 'agency', count: 4 }],
                        }),
                    ),
                ),
            ),
        );
        render(
            <SessionPage
                session={session({ can_join: true, status: 'live' })}
                simulated
            />,
        );
        await userEvent.click(screen.getByRole('button', { name: 'Join' }));

        await userEvent.click(
            await screen.findByRole('radio', { name: 'Whiteboard' }),
        );
        expect(
            await screen.findByRole('application', {
                name: 'Shared whiteboard',
            }),
        ).toBeInTheDocument();

        postJson.mockResolvedValueOnce({});
        await userEvent.click(
            screen.getByRole('button', { name: 'Send practice questions' }),
        );
        await userEvent.click(
            await screen.findByRole('button', { name: 'Agency' }),
        );
        expect(postJson).toHaveBeenLastCalledWith('/sessions/s-1/shares', {
            kind: 'questions',
            topic: 'agency',
        });
        expect(router.reload).toHaveBeenCalledWith({ only: ['session'] });
    });

    it('lets everyone answer shared practice questions', async () => {
        render(
            <SessionPage
                session={session({
                    status: 'ended',
                    shares: [
                        {
                            id: 2,
                            kind: 'questions',
                            at: '2026-11-24T21:20:00Z',
                            by: 'Dr. Rachel Hughes',
                            lesson: null,
                            topic: 'agency',
                            questions: [
                                {
                                    id: 3,
                                    question:
                                        'What does a buyer’s agent owe the buyer?',
                                    options: [
                                        'Nothing',
                                        'Loyalty and disclosure',
                                    ],
                                    correct: 1,
                                    explanation:
                                        'Agents owe their clients loyalty.',
                                    source: 'TREC Rule 535.2',
                                },
                            ],
                        },
                    ],
                })}
                simulated
            />,
        );
        await userEvent.click(screen.getByRole('button', { name: 'Nothing' }));
        expect(screen.getByRole('status')).toHaveTextContent(
            'Not quite. Agents owe their clients loyalty. Source: TREC Rule 535.2',
        );
    });

    it('asks everyone before recording, and shows Recording with Stop', async () => {
        postJson.mockResolvedValue({});
        const asking = {
            id: 'r-1',
            status: 'asking' as const,
            requested_by: 'Dr. Rachel Hughes',
            started_at: null,
            stopped_at: null,
            duration_seconds: null,
            expires_at: null,
            play_url: null,
            agreed: ['Dr. Rachel Hughes'],
            waiting: ['Sofia Reyes'],
            my_answer: null,
        };
        const { rerender } = render(
            <SessionPage
                session={session({
                    me: 'guest',
                    can_join: true,
                    status: 'live',
                    recordings: [asking],
                })}
                simulated
            />,
        );
        postJson.mockResolvedValueOnce({
            simulated: true,
            url: null,
            token: null,
            ends_at: new Date(Date.now() + 30 * 60_000).toISOString(),
        });
        await userEvent.click(screen.getByRole('button', { name: 'Join' }));
        expect(
            await screen.findByText(
                'Dr. Rachel Hughes asks to record this session.',
            ),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Agree to record' }),
        );
        expect(postJson).toHaveBeenLastCalledWith(
            '/sessions/s-1/recordings/r-1/answer',
            { agreed: true },
        );

        rerender(
            <I18nextProvider i18n={i18n}>
                <SessionPage
                    session={session({
                        me: 'guest',
                        can_join: true,
                        status: 'live',
                        recordings: [
                            { ...asking, status: 'recording', my_answer: true },
                        ],
                    })}
                    simulated
                />
            </I18nextProvider>,
        );
        await userEvent.click(
            await screen.findByRole('button', { name: 'Stop recording' }),
        );
        expect(postJson).toHaveBeenLastCalledWith(
            '/sessions/s-1/recordings/r-1/stop',
            {},
        );
    });

    it('asks someone joining while it records to agree first', async () => {
        postJson.mockResolvedValue({
            simulated: true,
            url: null,
            token: null,
            ends_at: new Date(Date.now() + 30 * 60_000).toISOString(),
        });
        render(
            <SessionPage
                session={session({
                    me: 'guest',
                    can_join: true,
                    status: 'live',
                    recordings: [
                        {
                            id: 'r-1',
                            status: 'recording',
                            requested_by: 'Dr. Rachel Hughes',
                            started_at: '2026-11-24T21:05:00Z',
                            stopped_at: null,
                            duration_seconds: null,
                            expires_at: null,
                            play_url: null,
                            agreed: ['Dr. Rachel Hughes'],
                            waiting: [],
                            my_answer: null,
                        },
                    ],
                })}
                simulated
            />,
        );
        expect(
            screen.getByRole('heading', {
                name: 'This session is being recorded',
            }),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Agree and join' }),
        );
        expect(postJson).toHaveBeenNthCalledWith(
            1,
            '/sessions/s-1/recordings/r-1/answer',
            { agreed: true },
        );
        expect(postJson).toHaveBeenNthCalledWith(2, '/sessions/s-1/join');
    });

    it('lists finished recordings with when they are deleted', () => {
        render(
            <SessionPage
                session={session({
                    status: 'ended',
                    recordings: [
                        {
                            id: 'r-1',
                            status: 'ready',
                            requested_by: 'Dr. Rachel Hughes',
                            started_at: '2026-11-24T21:05:00Z',
                            stopped_at: '2026-11-24T21:45:00Z',
                            duration_seconds: 2400,
                            expires_at: '2027-02-22T21:45:00Z',
                            play_url:
                                '/sessions/recordings/r-1/file?signature=x',
                            agreed: ['Dr. Rachel Hughes', 'Sofia Reyes'],
                            waiting: [],
                            my_answer: true,
                        },
                    ],
                })}
                simulated
            />,
        );
        expect(screen.getByLabelText('Watch recording')).toHaveAttribute(
            'src',
            '/sessions/recordings/r-1/file?signature=x',
        );
        expect(
            screen.getByText(
                /40 min · Kept until Feb 2[23], 2027 · Agreed: Dr. Rachel Hughes, Sofia Reyes/,
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Delete recording' }),
        ).toBeInTheDocument();
    });
});
