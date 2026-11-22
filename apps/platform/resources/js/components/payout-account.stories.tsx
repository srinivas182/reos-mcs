import type { Meta, StoryObj } from '@storybook/react-vite';
import { PayoutAccountCard } from '@/components/payout-account';

/** Where payouts go (B5.2b): a school or an instructor's Stripe account. */
const meta = {
    title: 'Payouts/Payout account',
    component: PayoutAccountCard,
    args: {
        account: null,
        connectUrl: '/organisation/sales/connect',
        ownUrl: '/organisation/sales/own-account',
    },
} satisfies Meta<typeof PayoutAccountCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const NotSetUp: Story = {};

export const StripeNeedsInformation: Story = {
    args: { account: { kind: 'express', state: 'needsInfo', requirements: 2 } },
};

export const Ready: Story = {
    args: {
        account: { kind: 'express', state: 'ready', requirements: 0 },
        ownUrl: null,
    },
};

export const OwnStripeAccount: Story = {
    args: { account: { kind: 'standard', state: 'ready', requirements: 0 } },
};
