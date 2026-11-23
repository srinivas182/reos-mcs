import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { ClassCard } from './class-card';
import { ClassReportView } from './class-report';
import { ClassPanel } from './class-panel';
import type { ClassActions } from './class-panel';
import { classCourses, classLive, classReport, classRow } from './fixtures';
import { ScheduleClassDialog } from './schedule-class-dialog';

const meta = {
    title: 'Learning/Live classes',
    component: ClassCard,
    args: { row: classRow(), onRegister: fn(), onUnregister: fn() },
} satisfies Meta<typeof ClassCard>;

export default meta;

type Story = StoryObj<typeof meta>;

const actions = (): ClassActions => ({
    hand: fn(),
    stage: fn(),
    poll: fn(),
    closePoll: fn(),
    vote: fn(),
    ask: fn(),
    like: fn(),
    answered: fn(),
    chat: fn(),
    sendCheck: fn(),
    answerCheck: fn(),
});

/** A CE class for Ana's course: 2 CE hours, 18 of 30 registered, Register. */
export const ToRegister: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('2 CE hours')).toBeInTheDocument();
        await userEvent.click(canvas.getByRole('button', { name: 'Register' }));
        await expect(args.onRegister).toHaveBeenCalled();
    },
};

/** Registered: open it, or cancel the registration before it starts. */
export const Registered: Story = {
    args: {
        row: classRow({
            me: 'registered',
            can_register: false,
            registered: 19,
        }),
    },
};

/** It's open: Join. */
export const Joinable: Story = {
    args: {
        row: classRow({
            me: 'registered',
            status: 'live',
            can_join: true,
            can_register: false,
        }),
    },
};

/** No seats left. */
export const Full: Story = {
    args: {
        row: classRow({ registered: 30, full: true, can_register: false }),
    },
};

/** The instructor's own class (i11), a review class without CE. */
export const Hosting: Story = {
    args: {
        row: classRow({
            me: 'host',
            ce: false,
            ce_hours: null,
            checkin_minutes: null,
            can_register: false,
        }),
    },
};

/** In class (L12): an attendance check to answer, the poll answered, questions with likes. */
export const StudentPanel: Story = {
    render: () => (
        <div className="max-w-md">
            <ClassPanel
                live={classLive({
                    check: {
                        id: 5,
                        closes_at: '2026-11-24T16:48:00Z',
                        answered: false,
                    },
                })}
                actions={actions()}
            />
        </div>
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByRole('alert')).toHaveTextContent(
            'Attendance check',
        );
        await expect(
            canvas.getByRole('button', { name: "I'm here" }),
        ).toBeInTheDocument();
    },
};

/** The poll before answering: the answers as buttons, no results yet. */
export const StudentPoll: Story = {
    render: () => (
        <div className="max-w-md">
            <ClassPanel
                live={classLive({
                    polls: [
                        {
                            id: 4,
                            question:
                                'What is the option period for this contract?',
                            options: [
                                { label: '5 days', votes: null },
                                { label: '7 days', votes: null },
                                { label: '10 days', votes: null },
                            ],
                            total: null,
                            my_vote: null,
                            closed: false,
                        },
                    ],
                })}
                actions={actions()}
            />
        </div>
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.click(canvas.getByRole('radio', { name: /Poll/ }));
        await expect(
            canvas.getByRole('button', { name: '7 days' }),
        ).toBeInTheDocument();
    },
};

/** Hosting (i12): questions most liked first, hands to bring on, attendance. */
export const HostPanel: Story = {
    render: () => (
        <div className="max-w-md">
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
                        {
                            id: 32,
                            name: 'Marco Diaz',
                            since: null,
                            on_stage: true,
                        },
                    ],
                    checks: [
                        {
                            id: 5,
                            opens_at: '2026-11-24T16:45:00Z',
                            closes_at: '2026-11-24T16:48:00Z',
                            answered: 16,
                            of: 18,
                        },
                    ],
                    next_check_at: '2026-11-24T17:00:00Z',
                })}
                actions={actions()}
            />
        </div>
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getAllByRole('button', { name: 'Mark answered' }),
        ).toHaveLength(2);
        await userEvent.click(canvas.getByRole('radio', { name: /Hands/ }));
        await expect(
            canvas.getByRole('button', { name: 'Bring on' }),
        ).toBeInTheDocument();
        await userEvent.click(
            canvas.getByRole('radio', { name: /Attendance/ }),
        );
        await expect(canvas.getByText(/16 of 18 answered/)).toBeInTheDocument();
    },
};

/** Schedule a class (w505): a CE course carries the approval's checks. */
export const Schedule: Story = {
    render: () => (
        <ScheduleClassDialog
            open
            onOpenChange={() => undefined}
            courses={classCourses}
            lengths={[30, 45, 60, 90, 120, 180, 240]}
            onScheduled={() => undefined}
        />
    ),
};

/** After a CE class (i13): the instructor confirms CE. */
export const ReportReady: Story = {
    args: {
        row: classRow({
            me: 'host',
            status: 'ended',
            report: 'draft',
            can_register: false,
        }),
    },
};

/** A registered student's past class with its recording. */
export const WithRecording: Story = {
    args: {
        row: classRow({
            me: 'registered',
            status: 'ended',
            has_recording: true,
            can_register: false,
        }),
    },
};

/** The CE class report (i13): one borderline case to decide before sending. */
export const Report: Story = {
    render: () => (
        <ClassReportView report={classReport()} onDecide={fn()} onSend={fn()} />
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByRole('button', { name: 'Confirm and send to school' }),
        ).toBeDisabled();
        await expect(
            canvas.getByText('Decide 1 borderline case before sending.'),
        ).toBeInTheDocument();
    },
};

/** Sent: what the school's admin sees. */
export const ReportSent: Story = {
    render: () => (
        <ClassReportView
            report={classReport({
                status: 'sent',
                can_decide: false,
                can_send: false,
                sent_by: 'Nelle Hart',
                sent_at: '2026-11-24T19:05:00Z',
                counts: {
                    joined: 17,
                    earned: 15,
                    not_earned: 3,
                    review: 1,
                    undecided: 0,
                },
                students: classReport().students.map((s) =>
                    s.outcome === 'review'
                        ? {
                              ...s,
                              decision: 'accepted',
                              decision_reason:
                                  'Answered the last check in chat.',
                              earned: true,
                          }
                        : s,
                ),
            })}
            onDecide={fn()}
            onSend={fn()}
        />
    ),
};
