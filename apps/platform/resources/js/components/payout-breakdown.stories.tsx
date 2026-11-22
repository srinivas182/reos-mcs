import type { Meta, StoryObj } from '@storybook/react-vite';
import { PayoutBreakdown } from '@/components/payout-breakdown';

/** Texas Realty School's November payouts (screen 53, B5.2b). */
const meta = {
    title: 'Payouts/Payout breakdown',
    component: PayoutBreakdown,
    args: {
        feePercent: 10,
        totals: {
            gross: 18624000,
            discounts: 2140000,
            platform_fee: 1648400,
            payees: 980000,
            net: 13855600,
        },
    },
} satisfies Meta<typeof PayoutBreakdown>;

export default meta;
type Story = StoryObj<typeof meta>;

export const TexasRealtySchool: Story = {};
