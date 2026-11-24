import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { ContentCalendar } from './calendar';
import { Celebrations } from './celebrations';
import { calendarWeek, celebrationList } from './fixtures';

const meta = {
    title: 'Studio/Content calendar',
    component: ContentCalendar,
    args: {
        calendar: calendarWeek(),
        onMove: fn(),
        onReuse: fn(),
        onPlan: fn(),
    },
} satisfies Meta<typeof ContentCalendar>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A week of posts (B12.3): move one to another day with Move. */
export const Week: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getAllByText('Scheduled')).toHaveLength(5);
        await userEvent.click(
            canvas.getAllByRole('button', { name: 'Move' })[0],
        );
        const date = canvas.getByLabelText('Move to');
        await userEvent.clear(date);
        await userEvent.type(date, '2026-12-03');
        await userEvent.click(
            within(date.closest('form') as HTMLElement).getByRole('button', {
                name: 'Move',
            }),
        );
        await expect(args.onMove).toHaveBeenCalledWith(
            expect.objectContaining({
                id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8f01',
            }),
            '2026-12-03',
        );
    },
};

/** A post that went out can't move; it's reused on another day. */
export const Reuse: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getAllByRole('button', { name: 'Reuse' })[0],
        );
        const date = canvas.getByLabelText('Reuse on');
        await userEvent.type(date, '2026-12-10');
        await userEvent.click(
            within(date.closest('form') as HTMLElement).getByRole('button', {
                name: 'Reuse',
            }),
        );
        await expect(args.onReuse).toHaveBeenCalledWith(
            expect.objectContaining({ status: 'published' }),
            '2026-12-10',
        );
    },
};

/** Nothing planned: an empty state, with Plan a post on every day. */
export const Empty: Story = {
    args: {
        calendar: calendarWeek({
            scheduled: 0,
            days: calendarWeek().days.map((d) => ({ ...d, posts: [] })),
        }),
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Nothing planned')).toBeInTheDocument();
        await userEvent.click(
            canvas.getAllByRole('button', { name: 'Plan a post' })[1],
        );
        await expect(args.onPlan).toHaveBeenCalledWith('2026-11-30');
    },
};

/** Today's celebration (screen 163) and the ones waiting. */
export const Celebration: Story = {
    render: () => <Celebrations data={celebrationList()} />,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('First closing')).toBeInTheDocument();
        await expect(canvas.getByText('Anniversary')).toBeInTheDocument();
    },
};

/** No celebrations yet. */
export const NoCelebrations: Story = {
    render: () => <Celebrations data={{ today: null, waiting: [] }} />,
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('No celebrations yet'),
        ).toBeInTheDocument();
    },
};
