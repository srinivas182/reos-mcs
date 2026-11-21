import type { Meta, StoryObj } from '@storybook/react-vite';
import { ScoreSummary } from '@/components/score-summary';

/** The free quiz's score (candidate journey screen 7, on the web). */
const meta = {
    title: 'Onboarding/Score summary',
    component: ScoreSummary,
    parameters: { layout: 'padded' },
} satisfies Meta<typeof ScoreSummary>;

export default meta;
type Story = StoryObj<typeof meta>;

/** The journey's example: 40% with agency strong. */
export const StrongStart: Story = {
    args: {
        correct: 4,
        total: 10,
        topics: [
            { topic: 'math', level: 'start_here' },
            { topic: 'agency', level: 'strong' },
            { topic: 'contracts', level: 'learning' },
        ],
    },
};

export const SevenOfTen: Story = {
    args: {
        correct: 7,
        total: 10,
        topics: [
            { topic: 'finance', level: 'strong' },
            { topic: 'ownership', level: 'learning' },
        ],
    },
};
