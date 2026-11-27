import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { LogFiltersForm, LogTable } from './compliance-log';
import type { LogRow } from './compliance-log';

const rows: LogRow[] = [
    {
        id: 3,
        at: '2026-11-27T15:10:00Z',
        agent: 'James Miller',
        actor: 'Linda Park',
        channel: 'post',
        action: 'approved',
        subject: 'p-1',
        issues: [],
        note: 'The seller asked us to say this.',
    },
    {
        id: 2,
        at: '2026-11-27T15:05:00Z',
        agent: 'James Miller',
        actor: 'James Miller',
        channel: 'post',
        action: 'sent_to_broker',
        subject: 'p-1',
        issues: [
            {
                code: 'banned',
                phrase: 'motivated seller',
                citation: 'brokerage',
            },
        ],
        note: 'The seller asked us to say this.',
    },
    {
        id: 1,
        at: '2026-11-27T14:50:00Z',
        agent: 'Priya Shah',
        actor: 'Priya Shah',
        channel: 'text',
        action: 'fixed',
        subject: null,
        issues: [
            {
                code: 'fair_housing',
                phrase: 'great for kids',
                citation: '42 U.S.C. §3604(c)',
            },
        ],
        note: null,
    },
];

const meta = {
    title: 'Compliance/Log',
    component: LogTable,
    args: { rows },
} satisfies Meta<typeof LogTable>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Every check, fix, approval and send-back on every channel (B13.2). */
export const Entries: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Sent to broker')).toBeInTheDocument();
        await expect(
            canvas.getByText('“great for kids” · 42 U.S.C. §3604(c)'),
        ).toBeInTheDocument();
    },
};

/** Nothing logged yet. */
export const Empty: Story = {
    args: { rows: [] },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Nothing logged yet'),
        ).toBeInTheDocument();
    },
};

/** The filters: agent, channel, what happened and dates, with Export CSV. */
export const Filters: StoryObj<typeof LogFiltersForm> = {
    render: (args) => <LogFiltersForm {...args} />,
    args: {
        filters: {
            agent: null,
            channel: null,
            action: null,
            from: null,
            to: null,
        },
        agents: [{ id: 1, name: 'James Miller' }],
        channels: ['post', 'video', 'page', 'email', 'text'],
        actions: [
            'checked',
            'blocked',
            'fixed',
            'sent_to_broker',
            'approved',
            'sent_back',
        ],
        exportHref: '/organisation/compliance-log/export',
        onApply: fn(),
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.type(canvas.getByLabelText('From'), '2026-11-01');
        await userEvent.click(canvas.getByRole('button', { name: 'Show' }));
        await expect(args.onApply).toHaveBeenCalledWith(
            expect.objectContaining({ from: '2026-11-01' }),
        );
        await expect(
            canvas.getByRole('link', { name: 'Export CSV' }),
        ).toHaveAttribute('href', '/organisation/compliance-log/export');
    },
};
