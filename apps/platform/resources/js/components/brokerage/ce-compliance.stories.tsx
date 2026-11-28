import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { CeCompliance, Sponsorships } from './ce-compliance';
import { loneStarCe, loneStarSponsorships } from './ce-fixtures';

const meta = {
    title: 'Brokerage/CE compliance',
    component: CeCompliance,
    args: {
        table: loneStarCe,
        routes: {
            remind: '/storybook/organisation/ce/remind',
            assign: '/storybook/organisation/ce/assign',
        },
    },
} satisfies Meta<typeof CeCompliance>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Two agents behind, one of them on SAE. */
export const TwoBehind: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('button', {
                name: 'Remind all due agents',
            }),
        ).toBeEnabled();
    },
};

/** Joins and releases with TREC's confirmation, and releasing James with a handover. */
export const Sponsorship: Story = {
    render: () => (
        <Sponsorships
            board={loneStarSponsorships}
            route="/storybook/organisation/sponsorships/release"
        />
    ),
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Confirmed by TREC'),
        ).toBeInTheDocument();
    },
};
