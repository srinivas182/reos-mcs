import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { CoachPanel } from './coach';
import { coach, topicPlan } from './fixtures';
import { FixPlan } from './fix-plan';

const meta = {
    title: 'Candidate/Drills and fix plans',
    component: CoachPanel,
    args: { coach },
} satisfies Meta<typeof CoachPanel>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 38: the smart drill on the three weakest topics, and every mode ranked by what helps most. */
export const Hub: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText(
                'Today: Finance, Real estate math, and Texas rules',
            ),
        ).toBeInTheDocument();
    },
};

/** Screen 46: now against the goal, the steps with their minutes, the misses with why. */
export const FixAWeakTopic: Story = {
    render: () => <FixPlan plan={topicPlan} showMisses />,
};

/** The plan finished and the topic off the weak list. */
export const FixFinished: Story = {
    render: () => (
        <FixPlan
            plan={{
                ...topicPlan,
                now: 74,
                weak: false,
                completed: true,
                minutes: 0,
                steps: topicPlan.steps.map((s) => ({
                    ...s,
                    status: 'done',
                    results_id: s.kind === 'retest' ? 40 : null,
                })),
            }}
        />
    ),
};
