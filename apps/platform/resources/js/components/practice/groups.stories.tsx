import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { studyGroup } from './fixtures';
import { GroupCard } from './group-card';

const meta = {
    title: 'Candidate/Study groups',
    component: GroupCard,
    args: {
        group: studyGroup,
        onChallenge: () => undefined,
        onLeave: () => undefined,
    },
} satisfies Meta<typeof GroupCard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 52: this week's leaderboard, first names only. */
export const ThisWeek: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('64 questions · 81% right'),
        ).toBeInTheDocument();
    },
};

/** A school's class group from its invite link, not joined yet. */
export const ClassGroupInvite: Story = {
    args: {
        group: {
            ...studyGroup,
            name: 'January evening class',
            school: 'Texas Realty School',
            mine: false,
            leaderboard: [],
        },
        onJoin: () => undefined,
    },
};
