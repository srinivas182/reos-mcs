import type { Meta, StoryObj } from '@storybook/react-vite';
import { TenantStatusActions } from '@/components/tenant-status-actions';

/** Suspend or restore a tenant (super admin, screen 60). */
const meta = {
    title: 'Tenants/Status actions',
    component: TenantStatusActions,
    args: { tenant: { id: 3, name: 'Lone Star Realty', status: 'active' } },
} satisfies Meta<typeof TenantStatusActions>;

export default meta;
type Story = StoryObj<typeof meta>;

/** A live tenant offers Suspend (with a reason). */
export const Live: Story = {};

/** A suspended tenant offers Restore. */
export const Suspended: Story = {
    args: { tenant: { id: 4, name: 'Bayou Realty', status: 'suspended' } },
};
