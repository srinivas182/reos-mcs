import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { weeklyReport } from './fixtures';
import { WeeklyReportView } from './weekly-report';

const meta = {
    title: 'Candidate/Weekly progress',
    component: WeeklyReportView,
    args: { report: weeklyReport },
} satisfies Meta<typeof WeeklyReportView>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 55: Sofia's week, half her plan, readiness up 4 points. */
export const HalfThePlan: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('70 minutes')).toBeInTheDocument();
        await expect(
            canvas.getByText('68% (+4 this week)'),
        ).toBeInTheDocument();
    },
};

/** A first week: no readiness yet and nothing studied. */
export const FirstWeek: Story = {
    args: {
        report: {
            ...weeklyReport,
            minutes: 0,
            per_day: [0, 0, 0, 0, 0, 0, 0],
            questions: 0,
            cards: 0,
            readiness: { now: null, before: null, change: null },
            lessons: 0,
            hours: 0,
            weakest: null,
            streak: 0,
        },
    },
};
