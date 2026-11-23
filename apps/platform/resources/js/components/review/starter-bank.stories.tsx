import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { starterBank } from './fixtures';
import { StarterBank } from './starter-bank';

const meta: Meta<typeof StarterBank> = {
    title: 'Instructor/Starter bank',
    component: StarterBank,
    args: {
        data: starterBank,
        topics: [
            'agency',
            'contracts',
            'ownership',
            'finance',
            'math',
            'state_rules',
        ],
        difficulties: ['easy', 'medium', 'hard'],
        portions: ['national', 'state'],
    },
};

export default meta;
type Story = StoryObj<typeof StarterBank>;

/** Texas rules, the starter questions still to review (screen 45, A9.1). */
export const TexasRules: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('287 to review · 11 approved · 2 retired'),
        ).toBeInTheDocument();
        await expect(
            canvas.getByRole('link', { name: 'Texas rules (92)' }),
        ).toHaveAttribute('aria-current', 'page');
    },
};

/** Every question on the topic approved or retired. */
export const AllReviewed: Story = {
    args: { data: { ...starterBank, questions: [] } },
};
