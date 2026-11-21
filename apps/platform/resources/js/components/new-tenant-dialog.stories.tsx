import type { Meta, StoryObj } from '@storybook/react-vite';
import { fn } from 'storybook/test';
import { NewTenantDialog } from '@/components/new-tenant-dialog';
import { tenantOptions } from '@/components/tenant-fixtures';

/** New tenant (super admin, screen 59). */
const meta = {
    title: 'Tenants/New tenant',
    component: NewTenantDialog,
    args: { open: true, onOpenChange: fn(), options: tenantOptions },
} satisfies Meta<typeof NewTenantDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Open: Story = {};
