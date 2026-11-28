import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { moveFixture } from './fixtures';
import { MoveSummary } from './move-summary';

const meta = {
    title: 'Agents/Brokerage move',
    component: MoveSummary,
    args: { move: moveFixture() },
} satisfies Meta<typeof MoveSummary>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Joining another brokerage: what moves, stays and ends. */
export const Brokerage: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('Moving to Gulf Coast Homes'),
        ).toBeInTheDocument();
        await expect(canvas.getByText('7 CE hours')).toBeInTheDocument();
        await expect(
            canvas.getByText('Stays with Lone Star Realty'),
        ).toBeInTheDocument();
    },
};

/** Changing team inside the same brokerage: nothing ends. */
export const Team: Story = {
    args: {
        move: moveFixture({
            kind: 'team',
            from: { id: 3, name: 'The Katy Team' },
            to: { id: 4, name: 'Sugar Land Group' },
            stays: [],
            ends: [],
        }),
    },
};
