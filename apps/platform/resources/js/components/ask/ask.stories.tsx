import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { AskAnswer } from './ask-answer';
import { iabsThread, limitedAnswer, sponsoredAnswer } from './fixtures';

const meta = {
    title: 'Candidate/Ask',
    component: AskAnswer,
    args: {
        answer: iabsThread.messages[1]!,
        question: 'When is the IABS form given?',
        state: 'Texas',
        last: true,
        busy: false,
        onFollow: () => undefined,
    },
} satisfies Meta<typeof AskAnswer>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen f02: from the course, with the lesson and the second to watch. */
export const FromCourse: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('From your course content · Texas'),
        ).toBeInTheDocument();
        await expect(
            canvas.getByRole('link', { name: 'Watch at 3:20' }),
        ).toBeInTheDocument();
    },
};

/** Screen k11: beyond the course, paid for by the candidate's school. */
export const Sponsored: Story = { args: { answer: sponsoredAnswer } };

/** Screen k11: not covered and no sponsor; never a price. */
export const NotCovered: Story = { args: { answer: limitedAnswer } };
