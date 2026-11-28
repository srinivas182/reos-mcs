import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { SeatsCard } from './seats-card';

const meta = {
    title: 'Brokerage/Seats and who pays',
    component: SeatsCard,
    args: {
        route: '/storybook/organisation/seats',
        moreHref: '/settings/billing',
        plan: {
            seats: 142,
            used: 38,
            pending: 104,
            left: 0,
            payer: 'organisation',
            credits_per_seat: 300,
            credits_pooled: true,
            pool: 42600,
            candidate_pool: 5000,
            teams: 3,
            chosen: false,
        },
    },
} satisfies Meta<typeof SeatsCard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Lone Star Realty: 142 seats, the brokerage pays, credits pooled. */
export const LoneStarRealty: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByText('Pool this month: 42,600 credits'),
        ).toBeInTheDocument();
    },
};
