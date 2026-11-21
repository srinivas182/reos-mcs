import type { Meta, StoryObj } from '@storybook/react-vite';
import { loneStarLimits } from '@/components/tenant-fixtures';
import { TenantLimitsCard } from '@/components/tenant-limits-card';

/** "Limits and data" (super admin, screen 60). */
const meta = {
    title: 'Tenants/Limits and data',
    component: TenantLimitsCard,
    parameters: { layout: 'padded' },
    args: { limits: loneStarLimits, region: 'us-east-2' },
} satisfies Meta<typeof TenantLimitsCard>;

export default meta;
type Story = StoryObj<typeof meta>;

/** 142 seats the brokerage pays for, pooled credits, 600 requests a minute. */
export const LoneStarRealty: Story = {};

/** An association with no seat limit. */
export const NoSeatLimit: Story = {
    args: { limits: { ...loneStarLimits, seats: null, candidate_pool: 0 } },
};
