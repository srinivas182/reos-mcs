import type { Meta, StoryObj } from '@storybook/react-vite';
import { inviteOptions } from '@/components/invite-fixtures';
import { InviteSheet } from '@/components/invite-sheet';

/** Invite people (A4.3a): Lone Star Realty inviting agents, or admins from Brokerage settings. */
const meta = {
    title: 'Tenancy/Invite sheet',
    component: InviteSheet,
    args: {
        open: true,
        onOpenChange: () => {},
        options: inviteOptions(),
        tenant: 'Lone Star Realty',
    },
} satisfies Meta<typeof InviteSheet>;

export default meta;
type Story = StoryObj<typeof meta>;

export const InviteAgents: Story = {};

/** "Invite admins" on Brokerage settings (screen 41). */
export const InviteAdmins: Story = { args: { defaultRole: 'broker' } };

/** Before REOS turns on text messages; one seat left. */
export const EmailOnlyLastSeat: Story = {
    args: {
        options: inviteOptions({
            can_text: false,
            seats: {
                role: 'agent',
                limit: 142,
                used: 141,
                pending: 0,
                left: 1,
                payer: 'organisation',
            },
        }),
    },
};

/** A team leader inviting into Okoye Team. */
export const TeamLeader: Story = {
    args: {
        options: inviteOptions({
            roles: [{ value: 'agent', label: 'Licensed agent', seat: true }],
        }),
    },
};
