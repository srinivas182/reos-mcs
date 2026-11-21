import type { Meta, StoryObj } from '@storybook/react-vite';
import { fn } from 'storybook/test';
import type { QuizQuestion } from '@/components/quiz-card';
import { QuizCard } from '@/components/quiz-card';

const agency: QuizQuestion = {
    id: 1,
    topic: 'agency',
    question:
        "A buyer's agent learns the seller will accept less. The buyer asks. What should the agent do?",
    options: [
        'Keep it to themselves',
        'Share it with the buyer, their client',
        "Tell the seller's agent first",
        'Ask the broker to decide',
    ],
    correct_option: 1,
    explanation:
        "A buyer's agent owes the buyer loyalty and disclosure of facts that matter to them.",
    source: 'Licensing exam content outline · Agency: duties to clients',
    sample: true,
};

/** One free-quiz question (candidate journey screen 6, on the web). */
const meta = {
    title: 'Onboarding/Quiz question',
    component: QuizCard,
    parameters: { layout: 'padded' },
    args: { question: agency, choice: null, onChoose: fn() },
} satisfies Meta<typeof QuizCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const NotAnswered: Story = {};

/** Right: green, with the explanation and source. */
export const Correct: Story = { args: { choice: 1 } };

/** Wrong: the choice in red and the right answer marked. */
export const Incorrect: Story = { args: { choice: 3 } };
