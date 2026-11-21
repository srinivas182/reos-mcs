import type { Meta, StoryObj } from '@storybook/react-vite';
import { fn } from 'storybook/test';
import { MembershipList } from '@/components/membership-list';

/** A person's roles on the People screen, each in its organisation. */
const meta = {
    title: 'Display/Membership list',
    component: MembershipList,
    parameters: { layout: 'padded' },
} satisfies Meta<typeof MembershipList>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Dana Brooks mentors new agents at Lone Star Realty and sells there too. */
export const MentorAndAgent: Story = {
    args: {
        onRemove: fn(),
        memberships: [
            {
                role: 'mentor',
                organisation: {
                    id: 2,
                    name: 'Lone Star Realty',
                    type: 'brokerage',
                },
                admin: false,
            },
            {
                role: 'agent',
                organisation: {
                    id: 2,
                    name: 'Lone Star Realty',
                    type: 'brokerage',
                },
                admin: false,
            },
        ],
    },
};

/** Karen Webb runs member education at Texas REALTORS®. */
export const AssociationAdmin: Story = {
    args: {
        onRemove: fn(),
        memberships: [
            {
                role: 'association_admin',
                organisation: {
                    id: 5,
                    name: 'Texas REALTORS®',
                    type: 'association',
                },
                admin: true,
            },
        ],
    },
};

export const NoRolesYet: Story = {
    args: { memberships: [] },
};
