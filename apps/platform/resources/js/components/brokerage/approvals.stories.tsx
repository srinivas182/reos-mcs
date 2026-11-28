import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { loneStarQueue } from './approvals-fixtures';
import { ApprovalsQueue } from './approvals-queue';

const meta = {
    title: 'Brokerage/Approvals',
    component: ApprovalsQueue,
    args: {
        queue: loneStarQueue,
        routes: {
            act: (kind: string, id: string) =>
                `/storybook/organisation/approvals/${kind}/${id}`,
            settings: '/storybook/organisation/approvals/settings',
        },
    },
} satisfies Meta<typeof ApprovalsQueue>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Linda Park's Monday queue: James's post has waited past the 4-hour limit. */
export const MondayMorning: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('1 past your 4-hour limit'),
        ).toBeInTheDocument();
        await expect(
            canvas.getByRole('heading', { name: 'Post' }),
        ).toBeInTheDocument();
    },
};

/** Nothing waiting for an office admin who answers escalations. */
export const Cleared: Story = {
    args: {
        queue: {
            ...loneStarQueue,
            can: { posts: false, questions: true, settings: false },
            counts: { posts: 0, pages: 0, questions: 0, overdue: 0 },
            items: [],
        },
    },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Nothing waiting'),
        ).toBeInTheDocument();
    },
};
