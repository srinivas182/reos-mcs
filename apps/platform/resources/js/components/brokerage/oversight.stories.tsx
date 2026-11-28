import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { OversightBoard } from './oversight-board';
import { loneStarBoard } from './oversight-fixtures';

const meta = {
    title: 'Brokerage/Agents and deals',
    component: OversightBoard,
    args: {
        board: loneStarBoard,
        routes: {
            agent: (id: number) =>
                `/storybook/organisation/oversight/agents/${id}`,
            settings: '/storybook/organisation/oversight/settings',
        },
    },
} satisfies Meta<typeof OversightBoard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Linda Park's Monday: James has gone quiet; Priya's option period ends tomorrow. */
export const MondayMorning: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Quiet for 14 days'),
        ).toBeInTheDocument();
    },
};

/** Everyone on track. */
export const OnTrack: Story = {
    args: {
        board: {
            ...loneStarBoard,
            counts: { agents: 1, need_help: 0, deals_at_risk: 0 },
            agents: [loneStarBoard.agents[1]],
            at_risk: [],
        },
    },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('No deals at risk'),
        ).toBeInTheDocument();
    },
};
