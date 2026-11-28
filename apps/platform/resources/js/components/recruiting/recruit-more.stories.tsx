import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { loneStarSponsored, openAgents } from './recruit-fixtures';
import { OpenAgents, SponsoredHelp } from './recruit-more';

const meta = {
    title: 'Recruiting/Sponsored help and open agents',
    component: SponsoredHelp,
    args: { board: loneStarSponsored, onCap: () => {} },
} satisfies Meta<typeof SponsoredHelp>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Lone Star's pool and three sponsored candidates. */
export const Sponsored: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Pool balance: 995 credits'),
        ).toBeInTheDocument();
    },
};

/** No sponsored candidates yet. */
export const NoneSponsored: Story = {
    args: { board: { pool: 0, candidates: [] } },
};

/** Licensed agents open to a move. */
export const AgentsOpenToAMove: Story = {
    render: () => <OpenAgents agents={openAgents} onInvite={() => {}} />,
};

/** Nobody open to a move yet. */
export const NoAgents: Story = {
    render: () => <OpenAgents agents={[]} onInvite={() => {}} />,
};
