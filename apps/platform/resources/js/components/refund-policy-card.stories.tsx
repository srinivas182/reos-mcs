import type { Meta, StoryObj } from '@storybook/react-vite';
import { RefundPolicyCard } from '@/components/refund-policy-card';

/** The refund policy on screen 66 (B5.3). */
const meta = {
    title: 'Billing/Refund policy',
    component: RefundPolicyCard,
    args: {
        policy: {
            mode: 'full_and_partial',
            window_days: 7,
            max_progress_percent: 25,
        },
    },
} satisfies Meta<typeof RefundPolicyCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const SprintFiveDefaults: Story = {};
export const FullOnly: Story = {
    args: {
        policy: { mode: 'full_only', window_days: 7, max_progress_percent: 25 },
    },
};
export const NoRefunds: Story = {
    args: {
        policy: { mode: 'none', window_days: 7, max_progress_percent: 25 },
    },
};
