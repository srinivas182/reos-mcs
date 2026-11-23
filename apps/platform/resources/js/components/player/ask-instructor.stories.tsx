import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import type { AskAnswer } from './ask-instructor';
import { AskInstructor } from './ask-instructor';

const answer: AskAnswer = {
    answer: 'You give the Information About Brokerage Services (IABS) form at the first substantive contact with a prospective buyer, seller, landlord or tenant, so they know who you represent before they share anything private.',
    sources: [
        {
            lesson_id: 7,
            lesson: 'Agency basics',
            seconds: 245,
            page: null,
            heading: 'The IABS form',
            text: 'In Texas, a license holder gives the IABS at the first substantive contact.',
        },
    ],
    quiz: {
        prompt: 'When is the IABS form given?',
        options: [
            'At the first substantive contact',
            'At closing',
            'After the offer is accepted',
        ],
        correct: 0,
        explanation: 'TREC requires it at the first substantive contact.',
    },
    engine: 'ai',
    found: true,
};

const meta = {
    title: 'Learn/Ask the instructor',
    component: AskInstructor,
    args: { lessonId: 7, send: async () => answer },
} satisfies Meta<typeof AskInstructor>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The bar under the video. */
export const Bar: Story = {};

/** Asked mid-lesson: the answer with its source, Explain simpler, En español, Quiz me and Resume. */
export const Answered: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.type(
            canvas.getByRole('textbox'),
            'When do I give the IABS?',
        );
        await userEvent.click(canvas.getByRole('button', { name: 'Ask' }));
        await expect(
            await canvas.findByText(
                /first substantive contact with a prospective buyer/,
            ),
        ).toBeInTheDocument();
    },
};

/** The course doesn't cover it: says so, never guesses. */
export const NotCovered: Story = {
    args: {
        send: async () => ({
            answer: "This course doesn't cover that yet. Try other words, or ask about this lesson.",
            sources: [],
            quiz: null,
            engine: 'none',
            found: false,
        }),
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.type(
            canvas.getByRole('textbox'),
            'What is a 1031 exchange?',
        );
        await userEvent.click(canvas.getByRole('button', { name: 'Ask' }));
        await expect(
            await canvas.findByText(/doesn't cover that yet/),
        ).toBeInTheDocument();
    },
};
