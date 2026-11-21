import type { Meta, StoryObj } from '@storybook/react-vite';
import { TenantStatusBadge } from '@/components/tenant-status-badge';

/** A tenant's status on screens 59 and 60. */
const meta = {
    title: 'Tenants/Status badge',
    component: TenantStatusBadge,
    args: { status: 'active' },
} satisfies Meta<typeof TenantStatusBadge>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Live: Story = {};
export const Trial: Story = { args: { status: 'trial' } };
export const Suspended: Story = { args: { status: 'suspended' } };
export const Archived: Story = { args: { status: 'archived' } };
