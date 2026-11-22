import type { Meta, StoryObj } from '@storybook/react-vite';
import type { RefundableOrder } from '@/components/refund-dialog';
import { RefundDialog } from '@/components/refund-dialog';

const order: RefundableOrder = {
    uuid: '9b1c2f7e-3a41-4f0e-9d2a-1c5e7b8a6f10',
    product: 'Texas Pre-licence Complete',
    buyer: { name: 'Sofia Reyes', email: 'sofia.reyes@demo.reos.test' },
    gateway: 'stripe',
    environment: 'simulator',
    total: 43192,
    status: 'paid',
    paid_at: '2026-11-20T15:04:00Z',
    policy: {
        mode: 'full_and_partial',
        refundable: 43192,
        days: 2,
        window_days: 7,
        within_policy: true,
    },
};

/** Refund an order from screen 66 (B5.3), with the policy check. */
const meta = {
    title: 'Billing/Refund',
    component: RefundDialog,
    args: { order, onOpenChange: () => undefined },
} satisfies Meta<typeof RefundDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

export const WithinPolicy: Story = {};
export const OutsideTheWindow: Story = {
    args: {
        order: {
            ...order,
            policy: { ...order.policy, days: 12, within_policy: false },
        },
    },
};
