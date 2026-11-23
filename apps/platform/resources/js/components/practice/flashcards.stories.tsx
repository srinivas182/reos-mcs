import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { deck } from './fixtures';
import { FlashcardSession } from './flashcard-session';
import { SaveFlashcard } from './save-flashcard';
import { SpeedRound } from './speed-round';

const meta = {
    title: 'Candidate/Flashcards and speed round',
    component: FlashcardSession,
    args: { deck },
} satisfies Meta<typeof FlashcardSession>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 31: a burst ready to start, one card due and two new. */
export const Ready: Story = {};

/** A card turned over: Again, Hard or Got it. */
export const Answer: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getByRole('button', { name: 'Start a 2-minute burst' }),
        );
        await userEvent.click(
            canvas.getByRole('button', { name: 'Show the answer' }),
        );
        await expect(
            canvas.getByText('At a scheduled 78 percent loan-to-value.'),
        ).toBeInTheDocument();
    },
};

/** Nothing due. */
export const NothingDue: Story = {
    args: { deck: { due: 0, new_left: 0, total: 0, cards: [] } },
};

/** Screen 50: the speed round before it starts, with the best score. */
export const Speed: Story = { render: () => <SpeedRound best={27} /> };

/** Save as flashcard, from a check-in, Ask or answer review. */
export const Save: Story = {
    render: () => (
        <SaveFlashcard
            source="review"
            front="What does IABS stand for?"
            back="Information About Brokerage Services."
        />
    ),
};
