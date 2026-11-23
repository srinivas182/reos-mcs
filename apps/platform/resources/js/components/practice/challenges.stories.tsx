import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { ChallengeCard } from './challenge-card';
import { challengeBoard } from './fixtures';

const meta = {
    title: 'Candidate/Challenges',
    component: ChallengeCard,
    args: { challenge: challengeBoard },
} satisfies Meta<typeof ChallengeCard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 52: Sofia's challenge, with the link to copy and the board by points. */
export const YourChallenge: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('85 points · 9 of 10 right'),
        ).toBeInTheDocument();
        await expect(canvas.getByText('Still answering')).toBeInTheDocument();
    },
};

/** An incoming challenge, not yet accepted. */
export const Incoming: Story = {
    args: {
        challenge: {
            ...challengeBoard,
            mine: false,
            attempt_id: null,
            submitted: false,
            entries: [],
        },
        onAccept: () => undefined,
    },
};
