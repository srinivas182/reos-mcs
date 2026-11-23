import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { AgentToday } from './agent-today';
import { agentPlan, agentToday, scorecard } from './fixtures';
import { PlanView } from './plan-view';
import { ScorecardCard } from './scorecard-card';

const meta = {
    title: 'Agent/Today and plan',
    component: AgentToday,
    args: {
        today: agentToday(),
        planUrl: '/agent/plan',
        onDone: fn(async () => undefined),
    },
} satisfies Meta<typeof AgentToday>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Today (screen 14): one ranked list, each item with its source; Done hides it with Undo. */
export const RankedList: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Overdue')).toBeInTheDocument();
        await expect(
            canvas.getByText(/From Follow Up Boss/),
        ).toBeInTheDocument();
        await userEvent.click(
            canvas.getByRole('button', {
                name: 'Mark done: Call Maria Lopez back about pre-approval',
            }),
        );
        await expect(args.onDone).toHaveBeenCalled();
        await expect(
            canvas.queryByText('Call Maria Lopez back about pre-approval'),
        ).not.toBeInTheDocument();
    },
};

/** Day one (screen 15): three first steps until there are 20 contacts or a connected tool. */
export const DayOne: Story = {
    args: {
        today: agentToday({
            items: [],
            more: 0,
            day_one: [
                {
                    code: 'import_contacts',
                    minutes: 5,
                    done: false,
                    link: 'contacts',
                },
                {
                    code: 'announce_licence',
                    minutes: 2,
                    done: true,
                    link: 'announce',
                },
                {
                    code: 'connect_tools',
                    minutes: 3,
                    done: false,
                    link: 'connections',
                },
            ],
        }),
    },
};

/** Grow mode: pipeline stats and nothing left today. */
export const GrowClear: Story = {
    args: {
        today: agentToday({
            goal: 'grow',
            stats: [
                { key: 'pipeline', value: 9, target: null },
                { key: 'under_contract', value: 3, target: null },
                { key: 'hot_today', value: 0, target: null },
            ],
            items: [],
            more: 0,
        }),
    },
};

/** The 90-day plan (screen 10) with the First-Closing Promise. */
export const Plan: Story = {
    render: () => (
        <PlanView plan={agentPlan()} onTick={fn()} onPromise={fn()} />
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Week 3 of 13')).toBeInTheDocument();
        await expect(
            canvas.getByRole('button', { name: 'I’m in' }),
        ).toBeInTheDocument();
    },
};

/** The weekly scorecard (screen 110): conversations to fix. */
export const WeeklyScorecard: Story = {
    render: () => <ScorecardCard scorecard={scorecard()} />,
};
