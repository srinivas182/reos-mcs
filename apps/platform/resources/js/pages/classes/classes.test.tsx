import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ClassPanel } from '@/components/classes/class-panel';
import type { ClassActions } from '@/components/classes/class-panel';
import {
    classCourses,
    classLive,
    classRow,
} from '@/components/classes/fixtures';
import { i18n } from '@/lib/i18n';
import ClassesPage, { groupClasses } from '@/pages/classes/index';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/classes', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const reply = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
const lengths = [30, 45, 60, 90, 120, 180, 240];

describe('Live classes (A10.2b)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(async () => reply({ data: classRow() }));
        vi.stubGlobal('fetch', fetchMock);
        router.reload.mockClear();
    });

    afterEach(() => vi.unstubAllGlobals());

    it('groups classes into this week, next week, later and past', () => {
        const now = Date.now();
        const at = (days: number) => new Date(now + days * 86_400_000);
        const row = (id: string, days: number) =>
            classRow({
                id,
                starts_at: at(days).toISOString(),
                ends_at: new Date(at(days).getTime() + 3_600_000).toISOString(),
            });
        const groups = groupClasses(
            [row('past', -2), row('soon', 0.1), row('far', 30)],
            now,
        );

        expect(groups.past.map((r) => r.id)).toEqual(['past']);
        expect(groups.later.map((r) => r.id)).toEqual(['far']);
        expect(
            [...groups.thisWeek, ...groups.nextWeek].map((r) => r.id),
        ).toEqual(['soon']);
    });

    it('registers a student, and cancelling offers Undo', async () => {
        const user = userEvent.setup();
        const soon = new Date(Date.now() + 3_600_000);
        const { rerender } = render(
            <ClassesPage
                classes={[
                    classRow({
                        starts_at: soon.toISOString(),
                        ends_at: new Date(
                            soon.getTime() + 7_200_000,
                        ).toISOString(),
                    }),
                ]}
                can_schedule={false}
                courses={[]}
                lengths={lengths}
            />,
        );

        expect(screen.getByText('Texas Contract Update')).toBeInTheDocument();
        expect(screen.getByText('18 of 30 registered')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Register' }));
        await waitFor(() => expect(router.reload).toHaveBeenCalled());
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain('/registration');
        expect(fetchMock.mock.calls[0]?.[1]).toMatchObject({ method: 'POST' });

        rerender(
            <I18nextProvider i18n={i18n}>
                <ClassesPage
                    classes={[
                        classRow({
                            me: 'registered',
                            can_register: false,
                            starts_at: soon.toISOString(),
                            ends_at: new Date(
                                soon.getTime() + 7_200_000,
                            ).toISOString(),
                        }),
                    ]}
                    can_schedule={false}
                    courses={[]}
                    lengths={lengths}
                />
            </I18nextProvider>,
        );
        await user.click(
            screen.getByRole('button', { name: 'Cancel registration' }),
        );
        await waitFor(() =>
            expect(fetchMock.mock.calls[1]?.[1]).toMatchObject({
                method: 'DELETE',
            }),
        );
    });

    it('lets an instructor schedule a class for a course they teach', async () => {
        const user = userEvent.setup();
        render(
            <ClassesPage
                classes={[]}
                can_schedule
                courses={classCourses}
                lengths={lengths}
            />,
        );

        expect(screen.getByText('No classes scheduled')).toBeInTheDocument();
        await user.click(
            screen.getAllByRole('button', { name: 'Schedule a class' })[0]!,
        );
        expect(
            screen.getByText(/CE class: attendance checks follow/),
        ).toBeInTheDocument();
        await user.type(
            screen.getByLabelText('Class name'),
            'Contract update: live review',
        );
        await user.clear(screen.getByLabelText('Seats'));
        await user.type(screen.getByLabelText('Seats'), '25');
        await user.click(
            screen.getByRole('button', { name: 'Schedule class' }),
        );

        await waitFor(() => expect(fetchMock).toHaveBeenCalled());
        const body = JSON.parse(
            String(fetchMock.mock.calls[0]?.[1]?.body),
        ) as Record<string, unknown>;
        expect(body).toMatchObject({
            course_id: 12,
            title: 'Contract update: live review',
            minutes: 60,
            capacity: 25,
        });
        await waitFor(() => expect(router.reload).toHaveBeenCalled());
    });
});

describe('the class tools (A10.2b: L12 and i12)', () => {
    const make = (): ClassActions => ({
        hand: vi.fn(),
        stage: vi.fn(),
        poll: vi.fn(),
        closePoll: vi.fn(),
        vote: vi.fn(),
        ask: vi.fn(),
        like: vi.fn(),
        answered: vi.fn(),
        chat: vi.fn(),
        sendCheck: vi.fn(),
        answerCheck: vi.fn(),
    });

    it('lets a student answer a check, raise a hand, ask, like and chat', async () => {
        const user = userEvent.setup();
        const actions = make();
        render(
            <ClassPanel
                live={classLive({
                    check: {
                        id: 5,
                        closes_at: '2026-11-24T16:48:00Z',
                        answered: false,
                    },
                })}
                actions={actions}
            />,
        );

        await user.click(screen.getByRole('button', { name: "I'm here" }));
        expect(actions.answerCheck).toHaveBeenCalledWith(5);
        await user.click(screen.getByRole('button', { name: 'Raise hand' }));
        expect(actions.hand).toHaveBeenCalledWith(true);

        await user.type(
            screen.getByLabelText('Ask the instructor'),
            'Who keeps the original?',
        );
        await user.click(screen.getByRole('button', { name: 'Ask' }));
        expect(actions.ask).toHaveBeenCalledWith('Who keeps the original?');
        await user.click(screen.getByRole('button', { name: /^Like\s*6$/ }));
        expect(actions.like).toHaveBeenCalledWith(41, false);

        await user.click(screen.getByRole('radio', { name: 'Chat' }));
        expect(
            screen.getByText('Can you share the slide with the new box 12?'),
        ).toBeInTheDocument();
        await user.type(screen.getByLabelText('Message the class'), 'Thanks!');
        await user.click(screen.getByRole('button', { name: 'Send' }));
        expect(actions.chat).toHaveBeenCalledWith('Thanks!');
    });

    it('lets the host launch a poll, bring a hand on and send a check', async () => {
        const user = userEvent.setup();
        const actions = make();
        render(
            <ClassPanel
                live={classLive({
                    me: { role: 'host', hand: false, on_stage: false },
                    hands: [
                        {
                            id: 31,
                            name: 'Ben Ortiz',
                            since: '2026-11-24T16:50:00Z',
                            on_stage: false,
                        },
                    ],
                })}
                actions={actions}
            />,
        );

        await user.click(screen.getByRole('radio', { name: 'Poll' }));
        await user.type(screen.getByLabelText('Question'), 'Who signs?');
        await user.type(screen.getByLabelText('Answer 1'), 'Buyer');
        await user.type(screen.getByLabelText('Answer 2'), 'Seller');
        await user.click(screen.getByRole('button', { name: 'Launch poll' }));
        expect(actions.poll).toHaveBeenCalledWith('Who signs?', [
            'Buyer',
            'Seller',
        ]);
        await user.click(screen.getByRole('button', { name: 'Close poll' }));
        expect(actions.closePoll).toHaveBeenCalledWith(3);

        await user.click(screen.getByRole('radio', { name: 'Hands (1)' }));
        await user.click(screen.getByRole('button', { name: 'Bring on' }));
        expect(actions.stage).toHaveBeenCalledWith(31, true);

        await user.click(screen.getByRole('radio', { name: 'Attendance' }));
        await user.click(
            screen.getByRole('button', { name: 'Send a check now' }),
        );
        expect(actions.sendCheck).toHaveBeenCalled();
    });
});
