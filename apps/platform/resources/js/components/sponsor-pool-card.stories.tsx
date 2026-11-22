import type { Meta, StoryObj } from '@storybook/react-vite';
import { SponsorPoolCard } from '@/components/sponsor-pool-card';

/** Texas Realty School's sponsor pools on its Plan tab (screen 60, B5.3). */
const meta = {
    title: 'Billing/Sponsor pools',
    component: SponsorPoolCard,
    args: { tenantId: 4, pools: { credits: 25000, seats: 142, funded: true } },
} satisfies Meta<typeof SponsorPoolCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Funded: Story = {};
export const NotFundedYet: Story = {
    args: { pools: { credits: 0, seats: 0, funded: false } },
};
