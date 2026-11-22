import type { Meta, StoryObj } from '@storybook/react-vite';
import { OrderSummary } from '@/components/order-summary';

/** The order summary on checkout (screen 3): price, sponsor discount, tax and total. */
const meta = {
    title: 'Billing/Order summary',
    component: OrderSummary,
    args: {
        product: 'Texas Pre-licence Complete',
        amount: 49900,
        discount: 10000,
        sponsor: 'Lone Star Realty',
        tax: 0,
        state: 'Texas',
    },
} satisfies Meta<typeof OrderSummary>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Sofia Reyes: Lone Star Realty pays $100. */
export const WithSponsorDiscount: Story = {};
export const BeforeTax: Story = {
    args: { discount: 0, sponsor: null, tax: null },
};
