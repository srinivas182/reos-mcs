import type { Meta, StoryObj } from '@storybook/react-vite';
import { fn } from 'storybook/test';
import { RoleSwitcher } from '@/components/role-switcher';
import type { Roles } from '@/types/auth';

const lindaPark: Roles = {
    held: [
        {
            role: 'broker',
            organisation: {
                id: 2,
                name: 'Lone Star Realty',
                type: 'brokerage',
            },
            admin: true,
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
    active: { role: 'broker', organisationId: 2 },
    permissions: ['learning.study', 'agents.manage', 'organisation.manage'],
    twoStepEnabled: true,
    twoStepRequired: false,
};

/**
 * The role in use, in the app header. People with several roles switch here;
 * each browser remembers its last role.
 */
const meta = {
    title: 'Navigation/Role switcher',
    component: RoleSwitcher,
    parameters: { layout: 'padded' },
    args: { onSwitch: fn(), roles: lindaPark },
} satisfies Meta<typeof RoleSwitcher>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Linda Park is the designated broker at Lone Star Realty and also an agent. */
export const BrokerWhoIsAlsoAnAgent: Story = {};

/** Before two-step is set up, admin roles say so in the list. */
export const AdminRoleNeedsTwoStep: Story = {
    args: {
        roles: {
            ...lindaPark,
            active: { role: 'agent', organisationId: 2 },
            twoStepEnabled: false,
        },
    },
};

/** Sofia Reyes has one role, so there's nothing to switch. */
export const OneRole: Story = {
    args: {
        roles: {
            held: [
                {
                    role: 'candidate',
                    organisation: {
                        id: 3,
                        name: 'Texas Realty School',
                        type: 'school',
                    },
                    admin: false,
                },
            ],
            active: { role: 'candidate', organisationId: 3 },
            permissions: ['learning.study'],
            twoStepEnabled: false,
            twoStepRequired: false,
        },
    },
};
