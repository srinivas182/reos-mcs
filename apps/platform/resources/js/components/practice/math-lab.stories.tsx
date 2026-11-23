import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { mathKinds, mathProblem } from './fixtures';
import { MathLab } from './math-lab';

const meta = {
    title: 'Candidate/Math lab',
    component: MathLab,
    args: { kinds: mathKinds },
} satisfies Meta<typeof MathLab>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 48: every kind, with how many solved. */
export const Kinds: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Loan-to-value'),
        ).toBeInTheDocument();
    },
};

/** A problem, the first step to work. */
export const Problem: Story = { args: { initial: mathProblem } };
