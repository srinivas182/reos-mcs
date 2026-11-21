import type { Meta, StoryObj } from '@storybook/react-vite';
import { TenantSupportAccess } from '@/components/tenant-support-access';

/** "Sign in as support (with consent)" on Tenant detail (screen 60, A4.1c). */
const meta = {
    title: 'Tenants/Support sign-in',
    component: TenantSupportAccess,
    parameters: { layout: 'padded' },
    decorators: [
        (Story) => (
            <div className="flex flex-wrap items-center justify-end gap-3">
                <Story />
            </div>
        ),
    ],
    args: {
        tenant: { id: 3, name: 'Lone Star Realty' },
        support: { can_ask: true, latest: null },
    },
} satisfies Meta<typeof TenantSupportAccess>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Nothing asked yet. */
export const Ask: Story = {};

/** Waiting for Linda Park, the broker. */
export const WaitingForOwner: Story = {
    args: {
        support: {
            can_ask: true,
            latest: {
                status: 'pending',
                mine: true,
                requester: 'Ops Srini',
                decided_by: null,
                requested_at: null,
                ends_at: null,
            },
        },
    },
};

/** Linda approved; support can start the 60 minutes. */
export const Approved: Story = {
    args: {
        support: {
            can_ask: true,
            latest: {
                status: 'approved',
                mine: true,
                requester: 'Ops Srini',
                decided_by: 'Linda Park',
                requested_at: null,
                ends_at: null,
            },
        },
    },
};

/** A tenant with no owner yet can't be asked. */
export const NoOwner: Story = {
    args: { support: { can_ask: false, latest: null } },
};
